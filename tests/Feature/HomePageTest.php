<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Support\Banner\BannerConfig;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, array $overrides = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create($overrides + [
            'category_id' => $category->id,
            'name' => "Board $n",
            'slug' => "board-$n",
            'sku' => "ET-TEST-$n",
            'short_description' => 'A test board.',
            'price_usd' => 10,
            'price_lkr' => 3000,
            'stock_status' => 'in_stock',
            'is_featured' => false,
        ]);
    }

    public function test_home_page_renders_with_an_empty_database(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('site.home.featured_empty_title'));
    }

    public function test_home_page_shows_the_banner_categories_and_only_featured_products(): void
    {
        $category = Category::create(['name' => 'Sensor Boards', 'slug' => 'sensor-boards', 'tagline' => 'Sense things.', 'sort_order' => 1]);
        $config = BannerConfig::fromLegacySlides([['heading' => 'First slide heading', 'sub_text' => 'Hidden slide heading']]);
        $config['slides'][0]['elements'][1]['visible'] = false; // hidden in the banner editor
        Banner::home()->update(['config' => $config]);
        $this->product($category, ['name' => 'Featured Widget', 'is_featured' => true]);
        $this->product($category, ['name' => 'Ordinary Widget']);

        $this->get('/')
            ->assertOk()
            ->assertSee('First slide heading')
            ->assertDontSee('Hidden slide heading')
            ->assertSee('Sensor Boards')
            ->assertSee('Featured Widget')
            ->assertDontSee('Ordinary Widget');
    }

    public function test_new_categories_appear_in_the_menu_automatically(): void
    {
        Category::create(['name' => 'Alpha Boards', 'slug' => 'alpha-boards', 'sort_order' => 1]);
        $this->get('/')->assertSee('Alpha Boards');

        // Saving a category clears the cached navigation list.
        Category::create(['name' => 'Beta Boards', 'slug' => 'beta-boards', 'sort_order' => 2]);
        $this->get('/')->assertSee('Beta Boards');
    }

    public function test_public_pages_never_link_to_content_studio(): void
    {
        $this->get('/')->assertOk()->assertDontSee('contentcreator');
    }

    public function test_wiki_link_opens_in_a_new_tab(): void
    {
        $this->get('/')->assertSee('href="'.route('wiki.index').'" target="_blank" rel="noopener"', false);
    }

    public function test_unset_contact_details_are_clearly_marked_as_placeholders(): void
    {
        $this->get('/')->assertSee('[PLACEHOLDER]');

        app(Settings::class)->set('contact.email', 'sales@edgetinker.test');
        app(Settings::class)->set('contact.phone', '+94 11 222 3333');
        app(Settings::class)->set('contact.address', '1 Test Road, Colombo');

        $this->get('/')
            ->assertDontSee('[PLACEHOLDER]')
            ->assertSee('mailto:sales@edgetinker.test', false);
    }

    public function test_styleguide_is_available_outside_production(): void
    {
        $this->get('/styleguide')->assertOk()->assertSee('EdgeTinker design system');
    }
}
