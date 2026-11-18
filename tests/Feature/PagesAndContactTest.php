<?php

namespace Tests\Feature;

use App\Livewire\Studio\Messages\Index as MessagesIndex;
use App\Livewire\Studio\Pages\Form as PageForm;
use App\Livewire\Studio\Settings as SettingsScreen;
use App\Models\ContactMessage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\ContentTypes\PageType;
use App\Studio\RevisionService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCatalogue;
use Tests\TestCase;

/** Services and Contact pages, the contact form, page editing and site settings. */
class PagesAndContactTest extends TestCase
{
    use BuildsCatalogue;
    use RefreshDatabase;

    private User $admin;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = User::factory()->admin()->create();
        $this->creator = User::factory()->create();
    }

    private function validMessage(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Nimal Perera', 'email' => 'nimal@example.com', 'company' => 'Acme',
            'subject' => 'Enquiry: EdgeCore', 'message' => 'Could you send me a quotation for 50 boards?',
        ];
    }

    private function pagePayload(string $slug, array $content = []): array
    {
        $payload = app(ContentTypeRegistry::class)->get('page')->current(PageType::idFor($slug));
        $payload['content'] = array_replace($payload['content'], $content);

        return $payload;
    }

    // ── Public pages ─────────────────────────────────────────────────────────

    public function test_services_page_lists_all_six_services_with_deliverables_and_enquiry_links(): void
    {
        $response = $this->get('/services')->assertOk();

        foreach (['Custom Electronics Development', 'PCB Design', 'Firmware Development', 'Enclosure Design', 'Web Application Development', 'Mobile App Development'] as $service) {
            $response->assertSee($service);
        }

        $response->assertSee('How we work')
            ->assertSee('Typical deliverables')
            ->assertSee(e(route('contact', ['subject' => 'Enquiry: PCB Design'])), false);
    }

    public function test_contact_page_prefills_the_subject_and_marks_unset_details_as_placeholders(): void
    {
        $this->get('/contact?subject='.urlencode('Enquiry: Test Board (ET-1)'))->assertOk()
            ->assertSee('value="Enquiry: Test Board (ET-1)"', false)
            ->assertSee('[PLACEHOLDER]');

        // A crafted subject is shown as text, never as markup.
        $this->get('/contact?subject='.urlencode('"><script>alert(1)</script>'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    // ── Contact form ─────────────────────────────────────────────────────────

    public function test_a_valid_message_is_stored_and_emailed(): void
    {
        app(Settings::class)->set('contact.recipient', 'sales@edgetinker.test');

        $this->post('/contact', $this->validMessage())->assertRedirect('/contact')->assertSessionHas('contact_sent');

        $message = ContactMessage::sole();
        $this->assertSame('Nimal Perera', $message->name);
        $this->assertNull($message->read_at);

        Notification::assertSentOnDemand(ContactMessageReceived::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'sales@edgetinker.test');

        $this->get('/contact')->assertOk(); // (flash is consumed by the redirect target in a real browser)
    }

    public function test_a_message_is_still_saved_when_no_recipient_is_configured(): void
    {
        $this->post('/contact', $this->validMessage())->assertSessionHas('contact_sent');

        $this->assertSame(1, ContactMessage::count());
        Notification::assertNothingSent();
    }

    public function test_invalid_messages_are_rejected_with_field_errors(): void
    {
        $this->from('/contact')->post('/contact', ['name' => '', 'email' => 'not-an-email', 'subject' => '', 'message' => 'short'])
            ->assertRedirect('/contact')
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_honeypot_drops_bots_without_telling_them(): void
    {
        $this->post('/contact', $this->validMessage(['website' => 'http://spam.example']))
            ->assertRedirect('/contact')->assertSessionHas('contact_sent');

        $this->assertSame(0, ContactMessage::count());
        Notification::assertNothingSent();
    }

    public function test_the_form_is_rate_limited_per_visitor(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->validMessage())->assertSessionHas('contact_sent');
        }

        $this->from('/contact')->post('/contact', $this->validMessage())->assertSessionHasErrors('message');
        $this->assertSame(5, ContactMessage::count());
    }

    public function test_header_injection_is_neutralised_in_the_notification_email(): void
    {
        $message = ContactMessage::create($this->validMessage(['subject' => "Hello\r\nBcc: victim@example.com", 'name' => "Eve\nX-Header: 1"]));

        $mail = (new ContactMessageReceived($message))->toMail(new \stdClass);

        $this->assertStringNotContainsString("\n", $mail->subject);
        $this->assertStringNotContainsString("\r", $mail->subject);
        $this->assertSame('nimal@example.com', $mail->replyTo[0][0]);
        $this->assertStringNotContainsString("\n", $mail->replyTo[0][1]);
    }

    // ── Page editing ─────────────────────────────────────────────────────────

    public function test_page_text_changes_only_after_approval(): void
    {
        $revisions = app(RevisionService::class);
        $revision = $revisions->save($this->creator, 'page', PageType::idFor('services'),
            $this->pagePayload('services', ['heading' => 'What we build for you']), submit: true);

        $this->get('/services')->assertSee('Engineering services')->assertDontSee('What we build for you');

        $revisions->approve($revision, $this->admin);

        $this->get('/services')->assertSee('What we build for you');
    }

    public function test_the_home_page_editor_controls_featured_products(): void
    {
        $category = $this->makeCategory('Boards');
        $first = $this->makeProduct($category, 'First Featured', ['is_featured' => true]);
        $second = $this->makeProduct($category, 'Second Featured');
        $third = $this->makeProduct($category, 'Third Featured');

        $payload = $this->pagePayload('home', [
            'featured_product_ids' => [$third->id, $second->id],
            'featured_heading' => 'Hand-picked boards',
        ]);

        app(RevisionService::class)->save($this->admin, 'page', PageType::idFor('home'), $payload, submit: true);

        $this->assertFalse($first->fresh()->is_featured, 'Products left off the list are no longer featured.');
        $this->assertTrue($second->fresh()->is_featured && $third->fresh()->is_featured);

        $this->get('/')->assertOk()
            ->assertSee('Hand-picked boards')
            ->assertSeeInOrder(['Third Featured', 'Second Featured'])   // the chosen order
            ->assertDontSee('First Featured');
    }

    public function test_partners_section_shows_logos_above_the_footer_and_is_hidden_when_empty(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="home-partners"', false);

        $payload = $this->pagePayload('home', [
            'partners_heading' => 'Trusted by',
            'partners' => [
                ['name' => 'Acme Robotics', 'logo' => ['path' => 'placeholders/partner-1.svg']],
                ['name' => 'Globex Power', 'logo' => ['path' => 'placeholders/partner-2.svg']],
            ],
        ]);
        app(RevisionService::class)->save($this->admin, 'page', PageType::idFor('home'), $payload, submit: true);

        $html = $this->get('/')->assertOk()->assertSee('Trusted by')->getContent();

        // Logos only: names appear as image alternatives, never as visible text.
        $this->assertStringContainsString('alt="Acme Robotics"', $html);
        $this->assertStringContainsString('alt="Globex Power"', $html);
        $this->assertStringNotContainsString('>Acme Robotics<', $html);
        $this->assertSame(1, substr_count($html, 'alt="Acme Robotics"'), 'Each partner is announced to screen readers once.');

        // The strip has two groups (for the seamless loop); the copy is hidden from assistive technology.
        $this->assertSame(2, substr_count($html, 'class="logo-marquee-group"'));
        $this->assertMatchesRegularExpression('#class="logo-marquee-group"\s+aria-hidden="true"#', $html);

        // It is the last section, directly before the footer.
        $partners = strpos($html, 'aria-labelledby="home-partners"');
        $footer = strpos($html, '<footer');
        $this->assertNotFalse($partners);
        $this->assertLessThan($partners, strpos($html, 'id="home-contact"'), 'Partners comes after the contact call-to-action.');
        $this->assertStringNotContainsString('<section', substr($html, $partners, $footer - $partners), 'No other section sits between Partners and the footer.');
        $this->assertMatchesRegularExpression('#^</section>\s*</main>\s*$#', substr($html, strrpos(substr($html, 0, $footer), '</section>'), $footer - strrpos(substr($html, 0, $footer), '</section>')));
    }

    public function test_a_partner_needs_a_logo(): void
    {
        $payload = $this->pagePayload('home', ['partners' => [['name' => 'No Logo Ltd', 'logo' => ['media_id' => null, 'path' => null]]]]);

        try {
            app(RevisionService::class)->save($this->creator, 'page', PageType::idFor('home'), $payload, submit: true);
            $this->fail('A partner without a logo must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('content.partners.0.logo.media_id', $e->errors());
        }
    }

    public function test_partner_logos_are_uploaded_from_the_home_page_editor(): void
    {
        Storage::fake('public');
        Queue::fake();

        Livewire::actingAs($this->admin)->test(PageForm::class, ['page' => 'home'])
            ->call('addRow', 'partners')
            ->set('form.content.partners.0.name', 'Initech')
            ->set('uploads.partners.0.logo', UploadedFile::fake()->image('initech.png', 300, 100))
            ->assertHasNoErrors()
            ->call('submit')
            ->assertHasNoErrors();

        $this->get('/')->assertSee('alt="Initech"', false)->assertSee(Media::sole()->path, false);
    }

    public function test_page_fields_are_validated(): void
    {
        $bad = $this->pagePayload('home', [
            'partners' => [['name' => '', 'logo' => ['path' => '../../secret.png']]],
            'categories_heading' => '',
            'featured_product_ids' => [999999],
        ]);

        try {
            app(RevisionService::class)->save($this->creator, 'page', PageType::idFor('home'), $bad, submit: true);
            $this->fail('Invalid page content must be refused.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing([
                'content.partners.0.name', 'content.partners.0.logo.path',
                'content.categories_heading', 'content.featured_product_ids.0',
            ], array_keys($e->errors()));
        }
    }

    public function test_pages_cannot_be_deleted(): void
    {
        $revisions = app(RevisionService::class);
        $request = $revisions->requestDeletion($this->creator, 'page', PageType::idFor('contact'));

        $this->expectException(ValidationException::class);
        $revisions->approve($request, $this->admin);
    }

    public function test_the_page_form_adds_rows_uploads_images_and_submits(): void
    {
        Storage::fake('public');
        Queue::fake();

        $component = Livewire::actingAs($this->creator)->test(PageForm::class, ['page' => 'home'])
            ->assertSet('slug', 'home')
            ->call('addRow', 'partners')
            ->set('form.content.partners.0.name', 'Uploaded partner')
            ->set('uploads.partners.0.logo', UploadedFile::fake()->image('logo.png', 300, 100))
            ->assertHasNoErrors();

        $this->assertSame(Media::sole()->id, $component->get('form.content.partners.0.logo.media_id'));

        // Only real image fields accept uploads.
        Livewire::actingAs($this->creator)->test(PageForm::class, ['page' => 'home'])
            ->set('uploads.why.0.title', UploadedFile::fake()->image('x.jpg'))->assertNotFound();

        $component->call('submit')->assertHasNoErrors()->assertRedirect(route('studio.pages.index'));

        $revision = Revision::sole();
        $this->assertSame('page', $revision->entity_type);
        $this->assertSame('Uploaded partner', $revision->payload['content']['partners'][0]['name']);
        $this->get('/')->assertDontSee('alt="Uploaded partner"', false); // nothing is live until approved

        // The preview renders the home page with the pending partner.
        $this->actingAs($this->admin)->get(route('studio.revisions.preview', $revision))->assertOk()
            ->assertSee('alt="Uploaded partner"', false)->assertSee('Preview only');
    }

    public function test_services_deliverables_are_edited_one_per_line(): void
    {
        Livewire::actingAs($this->admin)->test(PageForm::class, ['page' => 'services'])
            ->set('form.content.services.0.deliverables', "First thing\n\n  Second thing  \n")
            ->call('submit')->assertHasNoErrors();

        $this->get('/services')->assertSee('First thing')->assertSee('Second thing');
        $this->assertSame(['First thing', 'Second thing'], Page::where('slug', 'services')->sole()->content['services'][0]['deliverables']);
    }

    // ── Messages and settings (Content Admin) ────────────────────────────────

    public function test_messages_are_read_in_content_studio_by_the_admin_only(): void
    {
        $message = ContactMessage::create($this->validMessage());

        Livewire::actingAs($this->creator)->test(MessagesIndex::class)->assertForbidden();
        $this->actingAs($this->creator)->get('/contentcreator/messages')->assertForbidden();

        Livewire::actingAs($this->admin)->test(MessagesIndex::class)
            ->assertSee('Nimal Perera')
            ->call('open', $message->id)
            ->assertSee('Could you send me a quotation for 50 boards?');
        $this->assertNotNull($message->fresh()->read_at);

        Livewire::actingAs($this->admin)->test(MessagesIndex::class)->call('delete', $message->id);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_admin_sets_contact_details_and_the_site_updates(): void
    {
        $this->get('/contact')->assertSee('[PLACEHOLDER]');

        Livewire::actingAs($this->admin)->test(SettingsScreen::class)
            ->set('form.contact_email', 'hello@edgetinker.test')
            ->set('form.contact_phone', '+94 11 234 5678')
            ->set('form.contact_address', '12 Galle Road, Colombo 03')
            ->set('form.contact_map_embed_url', 'https://www.google.com/maps/embed?pb=abc123')
            ->set('form.social_github', 'https://github.com/edgetinker')
            ->call('save')->assertHasNoErrors();

        $this->get('/contact')->assertDontSee('[PLACEHOLDER]')
            ->assertSee('mailto:hello@edgetinker.test', false)
            ->assertSee('12 Galle Road, Colombo 03')
            ->assertSee('src="https://www.google.com/maps/embed?pb=abc123"', false);
        $this->get('/')->assertSee('https://github.com/edgetinker', false);
    }

    public function test_settings_reject_unsafe_values_and_are_admin_only(): void
    {
        Livewire::actingAs($this->admin)->test(SettingsScreen::class)
            ->set('form.contact_map_embed_url', 'https://evil.example/frame')
            ->set('form.social_github', 'javascript:alert(1)')
            ->set('form.contact_email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['form.contact_map_embed_url', 'form.social_github', 'form.contact_email']);

        Livewire::actingAs($this->creator)->test(SettingsScreen::class)->assertForbidden();
        $this->actingAs($this->creator)->get('/contentcreator/settings')->assertForbidden();
    }

    public function test_the_logo_is_replaceable_from_site_settings(): void
    {
        Storage::fake('public');
        Queue::fake();

        $this->get('/')->assertSee('Edge<span class="text-primary-600">Tinker</span>', false);

        Livewire::actingAs($this->admin)->test(SettingsScreen::class)
            ->set('logoUpload', UploadedFile::fake()->image('logo.png', 300, 80))->assertHasNoErrors();

        $logo = Media::sole();
        $this->get('/')->assertSee($logo->path, false)->assertDontSee('Edge<span class="text-primary-600">Tinker</span>', false);

        Livewire::actingAs($this->admin)->test(SettingsScreen::class)->call('removeLogo');
        $this->get('/')->assertSee('Edge<span class="text-primary-600">Tinker</span>', false);
    }

    public function test_new_studio_screens_open_for_the_right_roles(): void
    {
        foreach (['/pages', '/pages/home', '/pages/services', '/pages/contact', '/wiki', '/wiki/new'] as $path) {
            $this->actingAs($this->creator)->get('/contentcreator'.$path)->assertOk();
        }
        foreach (['/messages', '/settings'] as $path) {
            $this->actingAs($this->admin)->get('/contentcreator'.$path)->assertOk();
        }
        $this->actingAs($this->creator)->get('/contentcreator/pages/unknown')->assertNotFound();
    }
}
