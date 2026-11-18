<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WikiPage;
use App\Studio\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsCatalogue;
use Tests\TestCase;

/** Sitemap, robots, sharing tags, security headers and error pages. */
class SeoAndHardeningTest extends TestCase
{
    use BuildsCatalogue;
    use RefreshDatabase;

    // ── Sitemap and robots ───────────────────────────────────────────────────

    public function test_sitemap_lists_public_pages_and_nothing_private(): void
    {
        $category = $this->makeCategory('Sensor Boards');
        $product = $this->makeProduct($category, 'Mapped Board', ['slug' => 'mapped-board']);
        WikiPage::create(['product_id' => $product->id, 'title' => 'Overview', 'slug' => 'overview', 'body' => 'Text', 'sort_order' => 1]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'The sitemap must be well-formed XML.');

        $locations = [];
        foreach ($xml->url as $url) {
            $locations[] = (string) $url->loc;
        }

        foreach ([url('/'), url('/products'), url('/products/sensor-boards'), url('/product/mapped-board'), url('/services'), url('/contact'),
            url('/wiki'), url('/wiki/sensor-boards/mapped-board/overview')] as $expected) {
            $this->assertContains($expected, $locations);
        }

        $all = implode("\n", $locations);
        foreach (['contentcreator', 'styleguide', '/search', 'sitemap.xml'] as $private) {
            $this->assertStringNotContainsString($private, $all);
        }
    }

    public function test_sitemap_updates_when_content_is_approved(): void
    {
        Notification::fake();
        $category = $this->makeCategory('Boards');
        $this->get('/sitemap.xml')->assertDontSee('/product/fresh-board');

        $revisions = app(RevisionService::class);
        $revision = $revisions->save(User::factory()->create(), 'product', null,
            ['name' => 'Fresh Board', 'sku' => 'ET-FR-1', 'category_id' => $category->id, 'short_description' => 'New.'], submit: true);
        $this->get('/sitemap.xml')->assertDontSee('/product/fresh-board'); // still pending

        $revisions->approve($revision, User::factory()->admin()->create());
        $this->get('/sitemap.xml')->assertSee('/product/fresh-board');
    }

    public function test_robots_never_names_content_studio_and_blocks_non_production_copies(): void
    {
        // Tests run in a non-production environment: the whole copy is closed to crawlers.
        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /')
            ->assertDontSee('contentcreator');

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertOk()
            ->assertSee("Disallow:\n", false)
            ->assertDontSee('Disallow: /')
            ->assertSee('Sitemap: '.url('/sitemap.xml'))
            ->assertDontSee('contentcreator');
    }

    // ── Page metadata ────────────────────────────────────────────────────────

    public function test_public_pages_have_titles_descriptions_canonical_links_and_sharing_tags(): void
    {
        $category = $this->makeCategory('Sensor Boards', ['tagline' => 'Sense the world.']);
        $this->makeProduct($category, 'Meta Board', ['slug' => 'meta-board']);

        $pages = [
            '/' => 'EdgeTinker',
            '/products' => 'All products | EdgeTinker',
            '/products/sensor-boards' => 'Sensor Boards | EdgeTinker',
            '/product/meta-board' => 'Meta Board | EdgeTinker',
            '/services' => 'Engineering services | EdgeTinker',
            '/contact' => 'Contact Us | EdgeTinker',
        ];

        $titles = [];
        foreach ($pages as $url => $title) {
            $response = $this->get($url)->assertOk();
            $html = $response->getContent();

            $this->assertSame(1, preg_match('#<title>([^<]+)</title>#', $html, $match), "$url needs exactly one title");
            $this->assertStringContainsString($title, html_entity_decode($match[1]));
            $titles[] = $match[1];

            $this->assertMatchesRegularExpression('#<meta name="description" content="[^"]{10,}">#', $html, "$url needs a meta description");
            $response->assertSee('<link rel="canonical" href="'.url($url === '/' ? '' : $url).'">', false);
            $response->assertSee('property="og:title"', false)->assertSee('property="og:description"', false)->assertSee('property="og:url"', false);
            $this->assertSame(1, substr_count($html, '<h1'), "$url needs exactly one h1");
        }

        $this->assertCount(count($pages), array_unique($titles), 'Every page needs its own title.');
        $this->get('/')->assertSee('"@type":"WebSite"', false)->assertSee('"@type":"Organization"', false);
    }

    public function test_structured_data_is_valid_json_with_a_schema_org_context(): void
    {
        $category = $this->makeCategory('Sensor Boards');
        $this->makeProduct($category, 'Meta Board', ['slug' => 'meta-board']);

        foreach (['/', '/product/meta-board'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match), "$url has structured data");
            $data = json_decode($match[1], true);

            $this->assertIsArray($data, "$url structured data is valid JSON");
            // Regression: Blade once read "@context" as its own directive and printed PHP code into the key.
            $this->assertSame('https://schema.org', $data['@context'] ?? null, "$url declares the schema.org context");
            $this->assertStringNotContainsString('<?php', $match[1]);
        }
    }

    // ── Security headers ─────────────────────────────────────────────────────

    public function test_every_response_carries_security_headers(): void
    {
        foreach (['/', '/products', '/wiki', '/contentcreator/login', '/does-not-exist'] as $url) {
            $response = $this->get($url);

            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Permissions-Policy');
            $this->assertNotNull($response->headers->get('Referrer-Policy'));

            $csp = (string) $response->headers->get('Content-Security-Policy');
            foreach (["default-src 'self'", "object-src 'none'", "frame-ancestors 'self'", "form-action 'self'", "base-uri 'self'"] as $directive) {
                $this->assertStringContainsString($directive, $csp, "$url is missing the CSP directive: $directive");
            }
        }
    }

    public function test_pages_load_nothing_from_other_sites(): void
    {
        $this->makeProduct($this->makeCategory('Boards'), 'Local Board', ['slug' => 'local-board']);

        foreach (['/', '/products', '/product/local-board', '/services', '/contact', '/wiki', '/contentcreator/login'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // Scripts, styles, fonts and images must all come from this site (no CDNs).
            preg_match_all('#<(?:script|link|img|source|iframe)\b[^>]*\b(?:src|href)="(https?:)?//([^/"]+)#i', $html, $matches);
            $hosts = array_unique($matches[2]);
            $this->assertSame([], array_values(array_diff($hosts, [parse_url(url('/'), PHP_URL_HOST).(parse_url(url('/'), PHP_URL_PORT) ? ':'.parse_url(url('/'), PHP_URL_PORT) : '')])),
                "$url loads resources from another site");
        }
    }

    public function test_session_cookies_are_http_only_and_same_site(): void
    {
        $cookie = collect($this->get('/')->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_forms_require_a_csrf_token(): void
    {
        // Laravel skips CSRF checks while running tests unless the middleware is told otherwise.
        $this->withMiddleware();
        $this->app['env'] = 'production';

        $this->post('/contact', ['name' => 'x'])->assertStatus(419);
        $this->post('/contentcreator/login', ['email' => 'a@b.c', 'password' => 'x'])->assertStatus(419);
        $this->post('/currency', ['currency' => 'LKR'])->assertStatus(419);
    }

    // ── Error pages ──────────────────────────────────────────────────────────

    public function test_unknown_addresses_get_a_friendly_404_page(): void
    {
        foreach (['/no-such-page', '/product/no-such-product', '/products/no-such-category'] as $url) {
            $this->get($url)->assertNotFound()
                ->assertSee('We could not find that page')
                ->assertSee('Browse products')
                ->assertSee('noindex', false)
                ->assertSee('role="search"', false); // the site header and a search box are still there
        }
    }

    public function test_error_pages_render_without_the_database_or_compiled_assets(): void
    {
        foreach ([500 => 'Something went wrong', 503 => 'We will be right back', 419 => 'This page has expired', 429 => 'Too many requests'] as $status => $heading) {
            $html = view("errors.$status")->render();

            $this->assertStringContainsString($heading, $html);
            $this->assertStringNotContainsString('/build/', $html, 'Error pages must not depend on compiled assets.');
            $this->assertStringContainsString('noindex', $html);
        }
    }

    public function test_server_errors_do_not_leak_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/_boom', fn () => throw new \RuntimeException('secret database password in a stack trace'));

        $this->get('/_boom')->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertDontSee('secret database password')
            ->assertDontSee('RuntimeException');
    }
}
