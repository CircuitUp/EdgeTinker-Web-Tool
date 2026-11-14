<?php

namespace Tests\Feature;

use App\Livewire\Studio\Wiki\Form as WikiForm;
use App\Livewire\Studio\Wiki\Index as WikiIndex;
use App\Models\Category;
use App\Models\Product;
use App\Models\Revision;
use App\Models\User;
use App\Models\WikiPage;
use App\Studio\RevisionService;
use App\Support\WikiMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCatalogue;
use Tests\TestCase;

class WikiTest extends TestCase
{
    use BuildsCatalogue;
    use RefreshDatabase;

    private Category $category;

    private Product $product;

    private User $admin;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->category = $this->makeCategory('Sensor Boards');
        $this->product = $this->makeProduct($this->category, 'SenseKit One', ['slug' => 'sensekit-one']);
        $this->admin = User::factory()->admin()->create();
        $this->creator = User::factory()->create();
    }

    private function page(string $title, string $body = "## Heading\n\nSome text.", int $order = 1): WikiPage
    {
        return WikiPage::create(['product_id' => $this->product->id, 'title' => $title, 'slug' => \Str::slug($title), 'body' => $body, 'sort_order' => $order]);
    }

    // ── Markdown ─────────────────────────────────────────────────────────────

    public function test_markdown_supports_headings_tables_code_and_callouts(): void
    {
        $rendered = WikiMarkdown::render(<<<'MD'
        ## Getting started

        ### Wiring

        | Pin | Name |
        |---|---|
        | 1 | VIN |

        ```cpp
        int led = 13;
        ```

        > [!WARNING]
        > Do not exceed **5 V**.

        > [!TIP]
        > Use a short cable.

        > An ordinary quotation.

        ## Getting started
        MD);

        $html = $rendered['html'];

        $this->assertStringContainsString('<h2 id="getting-started">', $html);
        $this->assertStringContainsString('<h2 id="getting-started-2">', $html, 'Repeated headings get unique ids.');
        $this->assertSame(['getting-started', 'wiring', 'getting-started-2'], array_column($rendered['toc'], 'id'));
        $this->assertSame([2, 3, 2], array_column($rendered['toc'], 'level'));

        $this->assertStringContainsString('<div class="table-scroll"', $html);
        $this->assertStringContainsString('<div class="code-block">', $html);
        $this->assertStringContainsString('data-copy', $html);
        $this->assertStringContainsString('language-cpp', $html);

        $this->assertStringContainsString('class="callout callout-warning"', $html);
        $this->assertStringContainsString('class="callout callout-tip"', $html);
        $this->assertStringContainsString('<strong>5 V</strong>', $html);
        $this->assertStringNotContainsString('[!WARNING]', $html);
        $this->assertStringContainsString('<blockquote>', $html, 'A plain quotation stays a quotation.');
    }

    public function test_markdown_output_cannot_carry_scripts(): void
    {
        $html = WikiMarkdown::render(<<<'MD'
        <script>alert('xss')</script>
        <img src=x onerror="alert(1)">
        <iframe src="https://evil.example"></iframe>

        [bad link](javascript:alert(1)) and [data link](data:text/html;base64,PHNjcmlwdD4=)

        ![image](javascript:alert(2))

        [good](https://example.com) [file](/storage/media/2026/11/manual.pdf)
        MD)['html'];

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<img src="x"', $html);
        $this->assertDoesNotMatchRegularExpression('/<[^>]+\sonerror=/i', $html, 'No element may carry an event-handler attribute.');
        $this->assertDoesNotMatchRegularExpression('/(href|src)="\s*(javascript|data):/i', $html);

        // Raw HTML is shown as text instead.
        $this->assertStringContainsString('&lt;script&gt;', $html);

        $this->assertStringContainsString('href="https://example.com" rel="noopener noreferrer"', $html);
        $this->assertMatchesRegularExpression('/href="\/storage\/media\/2026\/11\/manual\.pdf"[^>]*class="download-link"[^>]*download/', $html);
    }

    public function test_non_english_text_survives_rendering(): void
    {
        $html = WikiMarkdown::render("## Température – 25 °C\n\nΩ µF “quoted” ශ්‍රී ලංකා")['html'];

        $this->assertStringContainsString('Température – 25 °C', $html);
        $this->assertStringContainsString('Ω µF “quoted” ශ්‍රී ලංකා', $html);
    }

    // ── Public wiki ──────────────────────────────────────────────────────────

    public function test_wiki_shows_the_tree_article_and_previous_next_links(): void
    {
        $this->page('Overview', "## Product description\n\nIntro.\n\n## Features\n\nList.", 1);
        $this->page('Quick Start', "## Connect\n\nPlug it in.", 2);
        $this->page('Pinout', '## Header', 3);

        $this->get('/wiki')->assertOk()->assertSee('Sensor Boards')->assertSee('SenseKit One')->assertSee('Quick Start');

        $this->get('/wiki/sensor-boards/sensekit-one')->assertRedirect('/wiki/sensor-boards/sensekit-one/overview');

        $this->get('/wiki/sensor-boards/sensekit-one/quick-start')->assertOk()
            ->assertSee('<h1', false)->assertSee('Quick Start')
            ->assertSee('Plug it in.')
            ->assertSee('rel="prev"', false)->assertSee('Overview')
            ->assertSee('rel="next"', false)->assertSee('Pinout')
            ->assertSee('Last updated')
            ->assertSee(route('products.show', 'sensekit-one'), false)        // links back to the product
            ->assertSee(route('home'), false)                                  // link back to the main site
            ->assertSee('aria-current="page"', false);

        $this->get('/wiki/sensor-boards/sensekit-one/overview')->assertSee('On this page')->assertSee('href="#features"', false);
    }

    public function test_unknown_wiki_addresses_return_404(): void
    {
        $this->page('Overview');

        $this->get('/wiki/sensor-boards/sensekit-one/missing')->assertNotFound();
        $this->get('/wiki/wrong-category/sensekit-one/overview')->assertNotFound();
        $this->get('/wiki/sensor-boards/no-such-product')->assertNotFound();

        $empty = $this->makeProduct($this->category, 'No Docs', ['slug' => 'no-docs']);
        $this->get('/wiki/sensor-boards/no-docs')->assertNotFound();
    }

    public function test_product_page_links_to_its_wiki_and_the_main_menu_opens_the_wiki_in_a_new_tab(): void
    {
        $this->get('/product/sensekit-one')->assertSee('href="'.route('wiki.index').'"', false);

        $this->page('Overview');
        $this->get('/product/sensekit-one')->assertSee('href="'.route('wiki.product', ['sensor-boards', 'sensekit-one']).'"', false);

        $this->get('/')->assertSee('href="'.route('wiki.index').'" target="_blank" rel="noopener"', false);
    }

    public function test_wiki_search_finds_pages_by_title_content_and_product(): void
    {
        $this->page('Overview', 'General introduction to the board.');
        $this->page('Pinout', 'The header exposes an interrupt line.', 2);
        $other = $this->makeProduct($this->category, 'Other Board', ['slug' => 'other-board']);
        WikiPage::create(['product_id' => $other->id, 'title' => 'Overview', 'slug' => 'overview', 'body' => 'Nothing relevant.', 'sort_order' => 1]);

        $this->get('/wiki/search?q=interrupt')->assertOk()->assertSee('1 page found')->assertSee('Pinout');
        $this->get('/wiki/search?q=pinout')->assertSee('1 page found');
        $this->get('/wiki/search?q=sensekit')->assertSee('2 pages found');
        $this->get('/wiki/search?q=zzzznothing')->assertSee('No pages found');
        $this->get('/wiki/search')->assertOk()->assertSee('Search the wiki');
        $this->get('/wiki/search?q[]=x')->assertOk();
        $this->get('/wiki/search?q='.urlencode('100% _ \\ "'))->assertOk();
        $this->get('/wiki/search?q=interrupt')->assertSee('noindex', false);
    }

    // ── Workflow ─────────────────────────────────────────────────────────────

    public function test_wiki_pages_go_through_approval(): void
    {
        $revisions = app(RevisionService::class);
        $revision = $revisions->save($this->creator, 'wiki_page', null, [
            'product_id' => $this->product->id, 'title' => 'Firmware & Software', 'body' => "## Flashing\n\nUse the bootloader.",
        ], submit: true);

        $this->assertSame('firmware-and-software', $revision->payload['slug']);
        $this->assertSame(0, WikiPage::count());
        $this->get('/wiki')->assertDontSee('SenseKit One'); // a product with no live pages is not listed
        $this->get('/wiki/sensor-boards/sensekit-one/firmware-and-software')->assertNotFound();

        $revisions->approve($revision, $this->admin);

        $this->get('/wiki')->assertSee('SenseKit One'); // the cached tree was refreshed
        $this->get('/wiki/sensor-boards/sensekit-one/firmware-and-software')->assertOk()->assertSee('Use the bootloader.');

        // An edit stays invisible until approved too.
        $page = WikiPage::sole();
        $edit = $revisions->save($this->creator, 'wiki_page', $page->id, ['body' => '## Flashing\n\nNew instructions.'] + $page->only(['product_id', 'title', 'slug', 'sort_order']), submit: true);
        $this->get($page->load('product.category')->url())->assertSee('Use the bootloader.')->assertDontSee('New instructions.');
        $revisions->approve($edit, $this->admin);
        $this->get($page->url())->assertSee('New instructions.');
    }

    public function test_two_pages_of_one_product_cannot_share_an_address_but_different_products_can(): void
    {
        $this->page('Overview');
        $revisions = app(RevisionService::class);

        try {
            $revisions->save($this->creator, 'wiki_page', null, ['product_id' => $this->product->id, 'title' => 'Overview', 'body' => 'Duplicate.'], submit: true);
            $this->fail('A duplicate page address within a product must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }

        $other = $this->makeProduct($this->category, 'Other Board');
        $revision = $revisions->save($this->creator, 'wiki_page', null, ['product_id' => $other->id, 'title' => 'Overview', 'body' => 'Fine.'], submit: true);
        $this->assertSame('overview', $revision->payload['slug']);
    }

    public function test_deleting_a_product_removes_its_wiki_pages(): void
    {
        $this->page('Overview');

        app(RevisionService::class)->requestDeletion($this->admin, 'product', $this->product->id);

        $this->assertSame(0, WikiPage::count());
        $this->get('/wiki')->assertDontSee('SenseKit One');
    }

    // ── Content Studio ───────────────────────────────────────────────────────

    public function test_the_standard_page_set_is_created_as_drafts_and_skips_existing_pages(): void
    {
        $this->page('Overview');

        Livewire::actingAs($this->creator)->test(WikiIndex::class)->call('addStandardPages', $this->product->id);

        $drafts = Revision::where('entity_type', 'wiki_page')->get();
        $this->assertCount(6, $drafts, 'Seven standard pages minus the one that already exists.');
        $this->assertTrue($drafts->every(fn (Revision $r) => $r->status->value === 'draft'));
        $this->assertEqualsCanonicalizing(
            ['quick-start', 'datasheet', 'pinout', 'schematics', 'firmware-and-software', 'downloads'],
            $drafts->map(fn (Revision $r) => $r->payload['slug'])->all(),
        );
        $this->assertSame(1, WikiPage::count());

        // Running it again adds nothing.
        Livewire::actingAs($this->creator)->test(WikiIndex::class)->call('addStandardPages', $this->product->id);
        $this->assertSame(6, Revision::count());
    }

    public function test_the_editor_previews_markdown_live_and_submits(): void
    {
        Livewire::actingAs($this->creator)->test(WikiForm::class)
            ->set('form.product_id', $this->product->id)
            ->set('form.title', 'Quick Start')
            ->assertSet('form.slug', 'quick-start')
            ->set('form.body', "## Step one\n\n> [!NOTE]\n> Read this first.\n\n<script>alert(1)</script>")
            ->assertSeeHtml('<h2 id="step-one">')
            ->assertSeeHtml('class="callout callout-note"')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('studio.wiki.index', ['product' => $this->product->id]));

        $this->assertSame('SenseKit One: Quick Start', Revision::sole()->title);
    }

    public function test_the_editor_requires_a_product_and_content(): void
    {
        Livewire::actingAs($this->creator)->test(WikiForm::class)
            ->set('form.title', 'Empty')->call('submit')
            ->assertHasErrors(['form.product_id', 'form.body']);
    }
}
