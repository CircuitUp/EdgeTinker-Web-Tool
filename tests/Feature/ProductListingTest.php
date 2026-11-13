<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Search\ProductFilters;
use App\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\BuildsCatalogue;
use Tests\TestCase;

/** Listing pages and URL-based filters (database-agnostic; search ranking is tested on MySQL). */
class ProductListingTest extends TestCase
{
    use BuildsCatalogue;
    use RefreshDatabase;

    private Category $dev;

    private Category $sensors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dev = $this->makeCategory('Development Boards', ['tagline' => 'Boards for prototyping.']);
        $this->sensors = $this->makeCategory('Sensor Boards');

        $this->makeProduct($this->dev, 'Alpha Dev Board', ['price_usd' => 20, 'price_lkr' => 6000], ['USB-C', 'Wi-Fi']);
        $this->makeProduct($this->dev, 'Beta Dev Board', ['price_usd' => 5, 'price_lkr' => 1500, 'stock_status' => 'out_of_stock'], ['USB-C']);
        $this->makeProduct($this->sensors, 'Gamma Sensor', ['price_usd' => 8, 'price_lkr' => 2400, 'stock_status' => 'low_stock'], ['I2C']);
    }

    public function test_all_products_page_lists_every_product(): void
    {
        $this->get('/products')->assertOk()
            ->assertSee(__('site.listing.all_products'))
            ->assertSee('3 products')
            ->assertSee('Alpha Dev Board')->assertSee('Beta Dev Board')->assertSee('Gamma Sensor')
            ->assertDontSee('noindex', false);
    }

    public function test_category_page_lists_only_its_products(): void
    {
        $this->get('/products/development-boards')->assertOk()
            ->assertSee('Boards for prototyping.')
            ->assertSee('2 products')
            ->assertSee('Alpha Dev Board')
            ->assertDontSee('Gamma Sensor');
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->get('/products/not-a-category')->assertNotFound();
    }

    public function test_an_empty_category_shows_a_helpful_empty_state(): void
    {
        $this->makeCategory('Empty Boards');

        $this->get('/products/empty-boards')->assertOk()
            ->assertSee(__('site.listing.empty_category'))
            ->assertSee(__('site.listing.browse_all'));
    }

    public function test_tag_filter_accepts_a_comma_list_or_an_array(): void
    {
        $this->get('/products?tags=usb-c,wi-fi')->assertSee('1 product')->assertSee('Alpha Dev Board')->assertDontSee('Beta Dev Board');
        $this->get('/products?tags[]=usb-c&tags[]=wi-fi')->assertSee('1 product')->assertSee('Alpha Dev Board');
        $this->get('/products?tags=usb-c')->assertSee('2 products');
    }

    public function test_price_and_stock_filters_work_from_the_url(): void
    {
        $this->get('/products?min=6&max=10')->assertSee('1 product')->assertSee('Gamma Sensor');
        $this->get('/products?in_stock=1')->assertSee('2 products')->assertDontSee('Beta Dev Board');

        $this->withCookie(Currency::COOKIE, 'LKR')->get('/products?min=5000')
            ->assertSee('1 product')->assertSee('Alpha Dev Board')->assertSee('Rs 6,000.00');
    }

    public function test_sort_order_follows_the_url(): void
    {
        $this->get('/products?sort=price_asc')->assertSeeInOrder(['Beta Dev Board', 'Gamma Sensor', 'Alpha Dev Board']);
        $this->get('/products?sort=price_desc')->assertSeeInOrder(['Alpha Dev Board', 'Gamma Sensor', 'Beta Dev Board']);
        $this->get('/products?sort=name')->assertSeeInOrder(['Alpha Dev Board', 'Beta Dev Board', 'Gamma Sensor']);
    }

    public function test_active_filters_are_shown_with_links_that_remove_them(): void
    {
        $this->get('/products?tags=usb-c,wi-fi&in_stock=1')->assertOk()
            ->assertSee(__('site.listing.clear_all'))
            ->assertSee('href="'.e(url('/products?tags=wi-fi&in_stock=1')).'"', false)   // remove USB-C
            ->assertSee('href="'.e(url('/products?tags=usb-c%2Cwi-fi')).'"', false)      // remove "in stock only"
            ->assertSee('noindex', false);
    }

    public function test_price_filter_chip_is_readable(): void
    {
        // Guards against the "≤" and "–" symbols being corrupted by a wrong file encoding.
        $this->get('/products?max=10')->assertSee('Price: ≤ $10.00');
        $this->get('/products?min=6')->assertSee('Price: ≥ $6.00');
        $this->get('/products?min=6&max=10')->assertSee('Price: $6.00 – $10.00');
    }

    public function test_filtered_empty_result_offers_to_clear_filters(): void
    {
        $this->get('/products/sensor-boards?tags=usb-c')->assertOk()
            ->assertSee(__('site.listing.empty_filtered'))
            ->assertSee('href="'.url('/products/sensor-boards').'"', false);
    }

    public function test_malformed_parameters_are_ignored_rather_than_erroring(): void
    {
        $this->get('/products?tags[a][b]=c&min=abc&max=-5&sort=evil&page=zz&in_stock=maybe')->assertOk()->assertSee('3 products');
        $this->get('/search?q[]=array&category[]=x')->assertOk();
        $this->get('/search?q='.str_repeat('a', 500))->assertOk();
    }

    public function test_search_page_can_filter_by_category_and_tag_without_a_search_term(): void
    {
        $this->get('/search?tags=i2c')->assertOk()->assertSee('1 product')->assertSee('Gamma Sensor');
        $this->get('/search?category=sensor-boards')->assertOk()->assertSee('Gamma Sensor')->assertDontSee('Alpha Dev Board');
    }

    public function test_pagination_keeps_the_filters_in_its_links(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->makeProduct($this->dev, "Filler $i", [], ['USB-C']);
        }

        $this->get('/products/development-boards?tags=usb-c&sort=name')->assertOk()
            ->assertSee('16 products')
            ->assertSee('tags=usb-c', false)
            ->assertSee('sort=name&amp;page=2', false);

        $this->get('/products/development-boards?tags=usb-c&sort=name&page=2')->assertOk()->assertSee('Filler 9');
    }

    public function test_switching_currency_drops_the_price_range_but_keeps_other_filters(): void
    {
        $this->from('/products?tags=usb-c&min=5&max=10&page=2&sort=name')
            ->post('/currency', ['currency' => 'LKR'])
            ->assertRedirect(url('/products?tags=usb-c&sort=name'));
    }

    public function test_currency_switch_never_redirects_to_another_site(): void
    {
        $this->withHeader('Referer', 'https://evil.example/phish?min=1')
            ->post('/currency', ['currency' => 'LKR'])
            ->assertRedirect(route('home'));
    }

    public function test_filters_are_normalised_from_the_request(): void
    {
        $request = Request::create('/products', 'GET', [
            'q' => "  esp32   dev \n", 'tags' => 'USB-C, wi-fi,,bad slug!,usb-c', 'min' => '5', 'max' => 'x', 'in_stock' => '1', 'sort' => 'price_asc',
        ]);

        $filters = ProductFilters::fromRequest($request, 'lkr');

        $this->assertSame('esp32 dev', $filters->query);
        $this->assertSame(['usb-c', 'wi-fi'], $filters->tags);
        $this->assertSame(5.0, $filters->min);
        $this->assertNull($filters->max);
        $this->assertTrue($filters->inStock);
        $this->assertSame('price_lkr', $filters->priceColumn());
        $this->assertSame(['q' => 'esp32 dev', 'tags' => 'usb-c,wi-fi', 'min' => '5', 'in_stock' => 1, 'sort' => 'price_asc'], $filters->toQuery());
    }
}
