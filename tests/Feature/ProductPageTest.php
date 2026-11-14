<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Sensor Boards', 'slug' => 'sensor-boards', 'sort_order' => 1]);
    }

    private function product(array $overrides = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create($overrides + [
            'category_id' => $this->category->id,
            'name' => "Test Board $n",
            'slug' => "test-board-$n",
            'sku' => "ET-TEST-$n",
            'short_description' => 'A short description.',
            'description' => '<p>Full description.</p>',
            'key_features' => ['First feature', 'Second feature'],
            'price_usd' => 19.99,
            'price_lkr' => 6000,
            'stock_status' => 'low_stock',
        ]);
    }

    public function test_product_page_shows_the_product_details(): void
    {
        $product = $this->product(['name' => 'EdgeSense One', 'slug' => 'edgesense-one', 'sku' => 'ET-SNS-1']);
        $product->specs()->create(['label' => 'Supply voltage', 'value' => '1.8–5 V', 'sort_order' => 1]);
        $product->images()->create(['path' => 'placeholders/a.svg', 'alt' => 'Front of the board', 'is_thumbnail' => true, 'sort_order' => 1]);
        $product->tags()->attach(Tag::create(['name' => 'I2C', 'slug' => 'i2c']));

        $this->get('/product/edgesense-one')
            ->assertOk()
            ->assertSee('EdgeSense One')
            ->assertSee('ET-SNS-1')
            ->assertSee('A short description.')
            ->assertSee('$19.99')
            ->assertSee('Low stock')
            ->assertSee('First feature')
            ->assertSee('Supply voltage')
            ->assertSee('1.8–5 V')
            ->assertSee('Front of the board')
            ->assertSee('Sensor Boards')
            ->assertSee(route('search', ['tags' => 'i2c']), false)
            ->assertSee(__('site.product.enquire'));
    }

    public function test_unknown_product_returns_404(): void
    {
        $this->get('/product/does-not-exist')->assertNotFound();
    }

    public function test_enquire_button_prefills_the_contact_subject(): void
    {
        $this->product(['name' => 'EdgeSense One', 'slug' => 'edgesense-one', 'sku' => 'ET-SNS-1']);

        $expected = route('contact', ['subject' => 'Enquiry: EdgeSense One (ET-SNS-1)']);

        $this->get('/product/edgesense-one')->assertSee(e($expected), false);
    }

    public function test_price_follows_the_selected_currency(): void
    {
        $this->product(['slug' => 'priced']);

        $this->withCookie(Currency::COOKIE, 'LKR')->get('/product/priced')
            ->assertSee('Rs 6,000.00')
            ->assertDontSee('$19.99');
    }

    public function test_missing_price_shows_price_on_request(): void
    {
        $this->product(['slug' => 'unpriced', 'price_usd' => null, 'price_lkr' => null]);

        $this->get('/product/unpriced')->assertSee(__('site.product.price_on_request'));
    }

    public function test_description_html_is_sanitised(): void
    {
        $this->product([
            'slug' => 'unsafe',
            'description' => '<p onclick="steal()">Safe <strong>text</strong></p><script>alert("xss")</script>'
                .'<a href="javascript:alert(1)">bad link</a><iframe src="https://evil.example"></iframe>',
        ]);

        $this->get('/product/unsafe')
            ->assertOk()
            ->assertSee('<strong>text</strong>', false)
            ->assertDontSee('alert("xss")', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('evil.example', false);
    }

    public function test_plain_text_fields_are_escaped(): void
    {
        $this->product(['slug' => 'escaped', 'name' => 'Board <script>alert(1)</script>']);

        $this->get('/product/escaped')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_related_products_come_from_the_same_category_and_exclude_the_product_itself(): void
    {
        $other = Category::create(['name' => 'Power', 'slug' => 'power', 'sort_order' => 2]);
        $this->product(['name' => 'Main Board', 'slug' => 'main-board']);
        $this->product(['name' => 'Sibling Board']);
        $this->product(['name' => 'Other Category Board', 'category_id' => $other->id]);

        $response = $this->get('/product/main-board')->assertOk();

        $response->assertSee('Sibling Board')->assertDontSee('Other Category Board');
        $this->assertSame(['Sibling Board'], $response->viewData('related')->pluck('name')->all());
    }

    public function test_page_has_seo_tags_and_product_structured_data(): void
    {
        $this->product(['name' => 'EdgeSense One', 'slug' => 'edgesense-one', 'sku' => 'ET-SNS-1', 'seo_title' => 'EdgeSense One sensor | EdgeTinker']);

        $response = $this->get('/product/edgesense-one');

        $response->assertSee('<title>EdgeSense One sensor | EdgeTinker</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('products.show', 'edgesense-one').'">', false)
            ->assertSee('property="og:type" content="product"', false)
            ->assertSee('property="og:title" content="EdgeSense One sensor | EdgeTinker"', false)
            ->assertSee('application/ld+json', false);

        $data = $response->viewData('structuredData');
        $this->assertSame('Product', $data['@type']);
        $this->assertSame('ET-SNS-1', $data['sku']);
        $this->assertSame('19.99', $data['offers'][0]['price']);
        $this->assertSame('USD', $data['offers'][0]['priceCurrency']);
        $this->assertSame('https://schema.org/LimitedAvailability', $data['offers'][0]['availability']);
    }

    public function test_product_cards_link_to_the_product_page(): void
    {
        $this->product(['slug' => 'linked', 'is_featured' => true]);

        $this->get('/')->assertSee('href="'.route('products.show', 'linked').'"', false);
    }
}
