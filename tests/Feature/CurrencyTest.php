<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Boards', 'slug' => 'boards', 'sort_order' => 1]);
        Product::create([
            'category_id' => $category->id, 'name' => 'Priced Board', 'slug' => 'priced-board', 'sku' => 'ET-P-1',
            'short_description' => 'Has both prices.', 'price_usd' => 12.5, 'price_lkr' => 3750,
            'stock_status' => 'in_stock', 'is_featured' => true,
        ]);
        Product::create([
            'category_id' => $category->id, 'name' => 'Unpriced Board', 'slug' => 'unpriced-board', 'sku' => 'ET-P-2',
            'short_description' => 'No price yet.', 'price_usd' => null, 'price_lkr' => null,
            'stock_status' => 'coming_soon', 'is_featured' => true,
        ]);
    }

    public function test_prices_default_to_usd(): void
    {
        $this->get('/')
            ->assertSee('$12.50')
            ->assertDontSee('Rs 3,750.00');
    }

    public function test_switching_to_lkr_sets_a_cookie_and_shows_lkr_prices(): void
    {
        $this->from('/')->post('/currency', ['currency' => 'LKR'])
            ->assertRedirect('/')
            ->assertCookie(Currency::COOKIE, 'LKR');

        $this->withCookie(Currency::COOKIE, 'LKR')->get('/')
            ->assertSee('Rs 3,750.00')
            ->assertDontSee('$12.50');
    }

    public function test_unsupported_currency_is_rejected(): void
    {
        $this->from('/')->post('/currency', ['currency' => 'EUR'])
            ->assertSessionHasErrors('currency')
            ->assertCookieMissing(Currency::COOKIE);
    }

    public function test_a_tampered_cookie_falls_back_to_usd(): void
    {
        $this->withCookie(Currency::COOKIE, 'XXX')->get('/')->assertSee('$12.50');
    }

    public function test_products_without_a_price_show_price_on_request(): void
    {
        $this->get('/')->assertSee(__('site.product.price_on_request'));
    }
}
