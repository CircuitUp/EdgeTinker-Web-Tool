<?php

namespace Tests\Feature\Banner;

use App\Livewire\Studio\Banner\Editor;
use App\Livewire\Studio\Revisions\Show as RevisionShow;
use App\Models\Banner;
use App\Models\Media;
use App\Models\Revision;
use App\Models\User;
use App\Studio\MediaLibrary;
use App\Studio\RevisionService;
use App\Support\Banner\BannerConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** The Content Studio banner editor: saving, approval, publishing, preview, uploads and fonts. */
class BannerEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->creator = User::factory()->create();
        Banner::home()->update(['config' => BannerConfig::fromLegacySlides([
            ['heading' => 'Original heading', 'sub_text' => 'Original sub-text', 'button_label' => 'Browse', 'button_url' => '/products', 'image_path' => 'placeholders/slide-1.svg'],
        ])]);
    }

    private function edited(callable $change): array
    {
        $config = Banner::home()->design();
        $change($config);

        return $config;
    }

    public function test_only_signed_in_studio_users_can_open_the_editor(): void
    {
        $this->get(route('studio.banner.edit'))->assertRedirect(route('studio.login'));

        $this->actingAs($this->creator)->get(route('studio.banner.edit'))->assertOk()
            ->assertSee('Home banner')->assertSee('Submit for approval')->assertDontSee('>Publish<', false);
        $this->actingAs($this->admin)->get(route('studio.banner.edit'))->assertOk()->assertSee('Publish');
    }

    public function test_the_editor_renders_the_banner_with_the_shared_slide_component(): void
    {
        $html = $this->actingAs($this->admin)->get(route('studio.banner.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('x-data="bannerEditor(', $html);
        $this->assertStringContainsString('class="bnr-stack"', $html, 'The canvas uses the same markup as the live banner.');
        $this->assertStringContainsString('Original heading', $html);
    }

    public function test_a_creator_saves_a_draft_then_submits_and_nothing_is_live_until_approved(): void
    {
        $config = $this->edited(fn (&$c) => $c['slides'][0]['elements'][0]['content'] = 'Creator heading');

        Livewire::actingAs($this->creator)->test(Editor::class)
            ->call('save', $config, false)
            ->assertRedirect(route('studio.banner.revision', Revision::sole()->id));

        $revision = Revision::sole();
        $this->assertSame('banner', $revision->entity_type);
        $this->assertSame('draft', $revision->status->value);
        $this->get('/')->assertSee('Original heading')->assertDontSee('Creator heading');

        // Opening the editor again continues the draft.
        Livewire::actingAs($this->creator)->test(Editor::class)->assertRedirect(route('studio.banner.revision', $revision->id));

        Livewire::actingAs($this->creator)->test(Editor::class, ['revision' => $revision->id])
            ->call('save', $config, true)
            ->assertRedirect(route('studio.revisions.index'));
        $this->assertSame('submitted', $revision->fresh()->status->value);
        $this->get('/')->assertDontSee('Creator heading');

        // The Content Admin sees what changed and approves it.
        $this->actingAs($this->admin)->get(route('studio.review.show', $revision))->assertOk()
            ->assertSee('Slide 1')->assertSee('Creator heading');
        app(RevisionService::class)->approve($revision->fresh(), $this->admin);

        $this->get('/')->assertSee('Creator heading')->assertDontSee('Original heading');
    }

    public function test_publishing_as_admin_goes_live_at_once_and_appears_on_the_home_page(): void
    {
        $config = $this->edited(function (&$c) {
            $c['slides'][0]['elements'][0]['position']['desktop'] = ['x' => 50, 'y' => 40, 'ax' => 50, 'ay' => 50];
            $c['slides'][0]['overlay']['desktop'] = ['enabled' => false];
            $c['slides'][0]['elements'][] = ['type' => 'button', 'content' => 'New button', 'href' => 'https://example.com/shop', 'newTab' => true];
        });

        $this->get('/')->assertOk(); // warm the cache: publishing must clear it

        Livewire::actingAs($this->admin)->test(Editor::class)
            ->call('save', $config, true)
            ->assertRedirect(route('studio.banner.edit'));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('--pos-d:absolute;--l-d:50%;--t-d:40%', $html);
        $this->assertStringContainsString('--ov-d:none;--ovo-d:0', $html, 'The overlay can be switched off.');
        $this->assertMatchesRegularExpression('#<a href="https://example.com/shop" class="bnr-el bnr-btn"[^>]*target="_blank" rel="noopener"\s*><span class="bnr-content">New button</span>#', $html);
        $this->assertSame('approved', Revision::sole()->status->value);
    }

    public function test_invalid_banners_are_refused_with_messages_the_editor_can_show(): void
    {
        $config = $this->edited(function (&$c) {
            $c['slides'][0]['elements'][2]['href'] = 'javascript:alert(1)';
            $c['slides'][0]['elements'][1]['content'] = '';
        });

        $result = Livewire::actingAs($this->admin)->test(Editor::class)->call('save', $config, true)->effects['returns'][0] ?? null;

        $this->assertArrayHasKey('slides.0.elements.2.href', $result['errors']);
        $this->assertArrayHasKey('slides.0.elements.1.content', $result['errors']);
        $this->assertSame(0, Revision::count());
        $this->get('/')->assertSee('Original heading');
    }

    public function test_preview_renders_the_unsaved_banner_with_the_live_templates(): void
    {
        $config = $this->edited(fn (&$c) => $c['slides'][0]['elements'][0]['content'] = 'Only in preview');

        $this->post(route('studio.banner.preview'), ['config' => json_encode($config)])->assertRedirect(route('studio.login'));

        $this->actingAs($this->creator)->post(route('studio.banner.preview'), ['config' => json_encode($config)])
            ->assertOk()->assertSee('Only in preview')->assertSee('Preview only')->assertSee('noindex', false);
        $this->actingAs($this->creator)->post(route('studio.banner.preview'), ['config' => 'not json'])
            ->assertOk()->assertDontSee('aria-roledescription="carousel"', false);
        $this->get('/')->assertDontSee('Only in preview');
    }

    public function test_reset_and_templates_return_the_default_design(): void
    {
        $component = Livewire::actingAs($this->creator)->test(Editor::class);

        $reset = $component->call('resetDesign', $this->edited(fn (&$c) => $c['slides'][0]['elements'][0]['typography']['fontSize']['desktop'] = 99))->effects['returns'][0];
        $this->assertSame(48, $reset['slides'][0]['elements'][0]['typography']['fontSize']['desktop']);
        $this->assertSame('Original heading', $reset['slides'][0]['elements'][0]['content']);

        $slide = $component->call('template', 'slide')->effects['returns'][0];
        $this->assertCount(3, $slide['elements']);
        $this->assertSame('button', $component->call('template', 'button')->effects['returns'][0]['type']);
        $this->assertSame('text', $component->call('template', 'text')->effects['returns'][0]['type']);
    }

    public function test_background_images_and_videos_are_uploaded_to_the_media_library(): void
    {
        Storage::fake('public');
        Queue::fake();

        $component = Livewire::actingAs($this->creator)->test(Editor::class)
            ->set('upload', UploadedFile::fake()->image('hero.jpg', 1920, 900));
        $image = $component->call('storeUpload', 'image')->effects['returns'][0];
        $this->assertSame('image', $image['media']['kind']);
        $this->assertSame(Media::sole()->id, $image['media']['id']);

        $component->set('upload', UploadedFile::fake()->create('loop.mp4', 2048, 'video/mp4'));
        $video = $component->call('storeUpload', 'video')->effects['returns'][0];
        $this->assertSame('video', $video['media']['kind']);

        $component->set('upload', UploadedFile::fake()->create('evil.php', 10, 'text/x-php'));
        $this->assertArrayHasKey('error', $component->call('storeUpload', 'video')->effects['returns'][0]);

        $library = $component->call('library', 'video')->effects['returns'][0];
        $this->assertSame(['loop.mp4'], array_column($library, 'name'));

        // A video background is saved and rendered (muted, looping, not announced when decorative).
        $config = $this->edited(function (&$c) use ($video) {
            $c['slides'][0]['background']['type'] = 'video';
            $c['slides'][0]['background']['video']['media_id'] = $video['media']['id'];
        });
        app(RevisionService::class)->save($this->admin, 'banner', Banner::home()->id, $config, submit: true);
        $this->get('/')->assertSee('<video class="bnr-bg"', false)->assertSee('muted loop playsinline', false);

        // Files used by the banner cannot be deleted from the media library.
        $this->expectException(ValidationException::class);
        app(MediaLibrary::class)->delete(Media::find($video['media']['id']), $this->admin);
    }

    public function test_google_fonts_are_downloaded_once_and_served_from_this_site(): void
    {
        Storage::fake('public');
        Http::fake([
            'fonts.googleapis.com/*' => Http::response("/* cyrillic */\n@font-face {\n  src: url(https://fonts.gstatic.com/s/roboto/cyr.woff2) format('woff2');\n  unicode-range: U+0400-045F;\n}\n"
                ."/* latin */\n@font-face {\n  font-family: 'Roboto';\n  src: url(https://fonts.gstatic.com/s/roboto/latin.woff2) format('woff2');\n  unicode-range: U+0000-00FF, U+0131;\n}\n"),
            'fonts.gstatic.com/*' => Http::response('WOFF2DATA'),
        ]);

        $result = Livewire::actingAs($this->creator)->test(Editor::class)->call('loadFont', 'roboto', 700, false)->effects['returns'][0];

        $this->assertStringContainsString('banner-fonts/roboto/font.css', $result['css']);
        Storage::disk('public')->assertExists('banner-fonts/roboto/700-normal-latin.woff2');
        Storage::disk('public')->assertMissing('banner-fonts/roboto/700-normal-cyrillic.woff2');
        $css = Storage::disk('public')->get('banner-fonts/roboto/font.css');
        $this->assertStringContainsString('font-display: swap', $css);
        $this->assertStringContainsString("src: url('700-normal-latin.woff2')", $css);
        $this->assertStringNotContainsString('gstatic', $css, 'Visitors never load fonts from Google.');

        Livewire::actingAs($this->creator)->test(Editor::class)->call('loadFont', 'roboto', 700, false);
        Http::assertSentCount(2); // one stylesheet + one font file: the second request used the stored copy

        // Site fonts need no download; unknown fonts are refused.
        $this->assertSame(['css' => null], Livewire::actingAs($this->creator)->test(Editor::class)->call('loadFont', 'inter', 400, false)->effects['returns'][0]);
        $this->assertArrayHasKey('error', Livewire::actingAs($this->creator)->test(Editor::class)->call('loadFont', '../../etc', 400, false)->effects['returns'][0]);
    }

    public function test_a_font_that_cannot_be_downloaded_falls_back_quietly(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 500)]);

        $result = Livewire::actingAs($this->creator)->test(Editor::class)->call('loadFont', 'lato', 400, false)->effects['returns'][0];
        $this->assertStringContainsString('could not be downloaded', $result['error']);

        // Published anyway: the element uses the next font in its stack, and no broken stylesheet is linked.
        $config = $this->edited(fn (&$c) => $c['slides'][0]['elements'][0]['typography']['fontFamily'] = 'lato');
        app(RevisionService::class)->save($this->admin, 'banner', Banner::home()->id, $config, submit: true);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString("font-family:'Lato', Arial, sans-serif", html_entity_decode($html, ENT_QUOTES));
        $this->assertStringNotContainsString('banner-fonts/lato', $html);
    }

    public function test_the_banner_cannot_be_deleted(): void
    {
        $request = app(RevisionService::class)->requestDeletion($this->creator, 'banner', Banner::home()->id);

        $this->expectException(ValidationException::class);
        app(RevisionService::class)->approve($request, $this->admin);
    }

    public function test_the_review_screen_links_back_to_the_editor(): void
    {
        $revision = app(RevisionService::class)->save($this->creator, 'banner', Banner::home()->id, Banner::home()->design(), submit: true);

        Livewire::actingAs($this->admin)->test(RevisionShow::class, ['revision' => $revision->id])->assertOk()
            ->assertSee(route('studio.banner.revision', $revision->id), false);
    }
}
