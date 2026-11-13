<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Search\ProductFilters;
use App\Search\ProductSearch;
use App\Search\ProductSearchResults;
use Tests\Concerns\BuildsCatalogue;
use Tests\MySqlTestCase;

/** Search behaviour against real MySQL FULLTEXT indexes. */
class ProductSearchTest extends MySqlTestCase
{
    use BuildsCatalogue;

    private Category $dev;

    private Category $power;

    private Category $sensors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dev = $this->makeCategory('Development Boards');
        $this->power = $this->makeCategory('DC/DC Converter Boards');
        $this->sensors = $this->makeCategory('Sensor Boards');

        $this->makeProduct($this->dev, 'EdgeCore ESP32-S3 Dev Board', [
            'sku' => 'ET-DEV-S3-01', 'short_description' => 'Dual-core Wi-Fi development board.',
            'description' => '<p>Supported by MicroPython and the Arduino core.</p>',
            'price_usd' => 14.90, 'price_lkr' => 4500,
        ], ['ESP32', 'Wi-Fi', 'USB-C']);

        $this->makeProduct($this->dev, 'EdgeCore RP2040 Mini', [
            'sku' => 'ET-DEV-RP-01', 'short_description' => 'Thumb-sized board with castellated edges.',
            'price_usd' => 6.90, 'price_lkr' => 2100,
        ], ['RP2040', 'USB-C']);

        $this->makeProduct($this->power, 'PowerStep 5V 3A Buck Converter', [
            'sku' => 'ET-PWR-BK-0503', 'short_description' => 'Synchronous step-down module.',
            'price_usd' => 5.50, 'price_lkr' => 1650,
        ], ['Buck', 'Power']);

        $this->makeProduct($this->power, 'PowerStep Isolated Supply', [
            'sku' => 'ET-PWR-ISO-2405', 'short_description' => 'Isolated 5 V supply for industrial systems.',
            'description' => '<p>Breaks ground loops when powering an ESP32 gateway.</p>',
            'price_usd' => 12.00, 'price_lkr' => 3600, 'stock_status' => 'coming_soon',
        ], ['Isolated', 'Power']);

        $this->makeProduct($this->sensors, 'SenseKit Environmental Sensor Board', [
            'sku' => 'ET-SNS-ENV-01', 'short_description' => 'Temperature, humidity and pressure.',
            'price_usd' => 7.40, 'price_lkr' => 2250, 'stock_status' => 'low_stock',
        ], ['I2C', 'Temperature']);

        $this->makeProduct($this->sensors, 'SenseKit 6-Axis IMU Board', [
            'sku' => 'ET-SNS-IMU-6X', 'short_description' => 'Accelerometer and gyroscope.',
            'price_usd' => null, 'price_lkr' => null, 'stock_status' => 'out_of_stock',
        ], ['I2C', 'Motion']);
    }

    private function search(string $query = '', array $filters = []): ProductSearchResults
    {
        return app(ProductSearch::class)->search(new ProductFilters(...['query' => $query] + $filters));
    }

    private function skus(ProductSearchResults $results): array
    {
        return $results->products->pluck('sku')->all();
    }

    // ── Matching ─────────────────────────────────────────────────────────────

    public function test_matches_the_full_product_name(): void
    {
        $this->assertSame(['ET-DEV-S3-01'], $this->skus($this->search('EdgeCore ESP32-S3 Dev Board')));
    }

    public function test_matches_a_partial_name_from_the_start_or_middle_of_a_word(): void
    {
        $this->assertSame(['ET-SNS-ENV-01'], $this->skus($this->search('environ')));   // word prefix
        $this->assertSame(['ET-SNS-ENV-01'], $this->skus($this->search('vironment'))); // inside a word
    }

    public function test_matches_a_full_sku_and_a_sku_fragment_in_any_letter_case(): void
    {
        $this->assertSame(['ET-PWR-BK-0503'], $this->skus($this->search('ET-PWR-BK-0503')));
        $this->assertSame(['ET-PWR-BK-0503'], $this->skus($this->search('bk-0503')));
        $this->assertSame(['ET-DEV-S3-01'], $this->skus($this->search('s3')));
    }

    public function test_matches_words_that_only_appear_in_the_description(): void
    {
        $this->assertSame(['ET-DEV-S3-01'], $this->skus($this->search('micropython')));
    }

    public function test_matches_tags_and_category_names(): void
    {
        $this->assertEqualsCanonicalizing(['ET-SNS-ENV-01', 'ET-SNS-IMU-6X'], $this->skus($this->search('i2c')));
        $this->assertEqualsCanonicalizing(['ET-DEV-S3-01', 'ET-DEV-RP-01'], $this->skus($this->search('usb-c')));
        $this->assertEqualsCanonicalizing(['ET-DEV-S3-01', 'ET-DEV-RP-01'], $this->skus($this->search('development')));
    }

    public function test_every_word_must_match_when_several_are_given(): void
    {
        $this->assertSame(['ET-PWR-ISO-2405'], $this->skus($this->search('isolated power')));
    }

    public function test_name_matches_rank_above_description_matches(): void
    {
        // "esp32" is in one product's name and only in another's description.
        $this->assertSame(['ET-DEV-S3-01', 'ET-PWR-ISO-2405'], $this->skus($this->search('esp32')));
    }

    public function test_an_exact_name_ranks_first_among_partial_matches(): void
    {
        $this->makeProduct($this->dev, 'Mini', ['sku' => 'ET-EXACT-1']);

        $this->assertSame('ET-EXACT-1', $this->skus($this->search('mini'))[0]);
    }

    // ── Typo tolerance ───────────────────────────────────────────────────────

    public function test_a_misspelt_ending_still_finds_the_product_and_offers_a_correction(): void
    {
        $results = $this->search('convertor');

        $this->assertTrue($results->loose);
        $this->assertContains('ET-PWR-BK-0503', $this->skus($results));
        $this->assertSame('converter', $results->didYouMean);
    }

    public function test_transposed_letters_get_a_did_you_mean_suggestion(): void
    {
        $this->assertSame('esp32', $this->search('esp23')->didYouMean);
        $this->assertSame('sensor board', $this->search('sensr board')->didYouMean);
    }

    public function test_no_suggestion_when_the_search_already_finds_plenty_or_nothing_is_close(): void
    {
        $this->assertNull($this->search('board')->didYouMean);

        $results = $this->search('xyzzyplugh');
        $this->assertSame(0, $results->products->total());
        $this->assertNull($results->didYouMean);
    }

    public function test_suggestions_follow_catalogue_changes(): void
    {
        $this->assertNull($this->search('zigbbe')->didYouMean); // also warms the cached word list

        $this->makeProduct($this->dev, 'LinkWave Zigbee Board');

        $this->assertSame('zigbee', $this->search('zigbbe')->didYouMean);
    }

    public function test_special_characters_cannot_break_the_query(): void
    {
        foreach (['+esp32 -board', '"unterminated', '(((', '100% _ \\', '@@@ ~~ <> *', "o'reilly; DROP TABLE products", '  '] as $query) {
            $this->assertInstanceOf(ProductSearchResults::class, $this->search($query), "Query failed: $query");
        }

        $this->assertSame(6, $this->search('   ')->products->total(), 'A blank search lists everything.');
        $this->assertContains('ET-DEV-S3-01', $this->skus($this->search('+esp32')));
    }

    // ── Filters ──────────────────────────────────────────────────────────────

    public function test_filters_by_category(): void
    {
        $results = $this->search('', ['category' => 'sensor-boards']);

        $this->assertEqualsCanonicalizing(['ET-SNS-ENV-01', 'ET-SNS-IMU-6X'], $this->skus($results));
    }

    public function test_selecting_several_tags_narrows_the_results(): void
    {
        $this->assertCount(2, $this->skus($this->search('', ['tags' => ['usb-c']])));
        $this->assertSame(['ET-DEV-S3-01'], $this->skus($this->search('', ['tags' => ['usb-c', 'esp32']])));
    }

    public function test_price_range_uses_the_selected_currency(): void
    {
        $usd = $this->search('', ['min' => 6.0, 'max' => 8.0, 'currency' => 'USD']);
        $this->assertEqualsCanonicalizing(['ET-DEV-RP-01', 'ET-SNS-ENV-01'], $this->skus($usd));

        $lkr = $this->search('', ['min' => 3000.0, 'max' => 5000.0, 'currency' => 'LKR']);
        $this->assertEqualsCanonicalizing(['ET-DEV-S3-01', 'ET-PWR-ISO-2405'], $this->skus($lkr));
    }

    public function test_in_stock_only_keeps_in_stock_and_low_stock_products(): void
    {
        $results = $this->search('', ['inStock' => true]);

        $this->assertEqualsCanonicalizing(
            ['ET-DEV-S3-01', 'ET-DEV-RP-01', 'ET-PWR-BK-0503', 'ET-SNS-ENV-01'],
            $this->skus($results),
        );
    }

    public function test_filters_combine_with_a_search_term(): void
    {
        $results = $this->search('board', ['category' => 'sensor-boards', 'inStock' => true]);

        $this->assertSame(['ET-SNS-ENV-01'], $this->skus($results));
    }

    public function test_reports_the_price_range_and_tags_available_in_scope(): void
    {
        $results = $this->search('', ['category' => 'development-boards', 'currency' => 'USD']);

        $this->assertSame(6.90, $results->priceMin);
        $this->assertSame(14.90, $results->priceMax);
        $this->assertSame(['ESP32' => 1, 'RP2040' => 1, 'USB-C' => 2, 'Wi-Fi' => 1], $results->tags->pluck('products_count', 'name')->all());
    }

    // ── Sorting and paging ───────────────────────────────────────────────────

    public function test_sorts_by_price_in_the_selected_currency_with_unpriced_products_last(): void
    {
        $asc = $this->skus($this->search('', ['sort' => 'price_asc']));
        $this->assertSame(['ET-PWR-BK-0503', 'ET-DEV-RP-01', 'ET-SNS-ENV-01', 'ET-PWR-ISO-2405', 'ET-DEV-S3-01', 'ET-SNS-IMU-6X'], $asc);

        $desc = $this->skus($this->search('', ['sort' => 'price_desc', 'currency' => 'LKR']));
        $this->assertSame(['ET-DEV-S3-01', 'ET-PWR-ISO-2405', 'ET-SNS-ENV-01', 'ET-DEV-RP-01', 'ET-PWR-BK-0503', 'ET-SNS-IMU-6X'], $desc);
    }

    public function test_sorts_by_name_and_by_newest(): void
    {
        $names = $this->search('', ['sort' => 'name'])->products->pluck('name')->all();
        $sorted = $names;
        sort($sorted, SORT_FLAG_CASE | SORT_STRING);
        $this->assertSame($sorted, $names);

        $this->assertSame('ET-SNS-IMU-6X', $this->skus($this->search('', ['sort' => 'newest']))[0]);
    }

    public function test_relevance_sort_falls_back_to_newest_without_a_search_term(): void
    {
        $this->assertSame('newest', (new ProductFilters(sort: 'relevance'))->effectiveSort());
        $this->assertSame('relevance', (new ProductFilters(query: 'esp32'))->effectiveSort());
    }

    public function test_results_are_paginated(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->makeProduct($this->dev, "Filler Board $i");
        }

        $results = $this->search();

        $this->assertSame(16, $results->products->total());
        $this->assertCount(12, $results->products->items());
        $this->assertSame(2, $results->products->lastPage());
    }

    // ── Over HTTP ────────────────────────────────────────────────────────────

    public function test_search_page_shows_results_and_the_did_you_mean_link(): void
    {
        $this->get('/search?q=esp32')->assertOk()
            ->assertSee('EdgeCore ESP32-S3 Dev Board')
            ->assertSee('2 results')
            ->assertSee('noindex', false);

        $this->get('/search?q=esp23')->assertOk()
            ->assertSee(__('site.listing.did_you_mean'))
            ->assertSee('href="'.e(route('search', ['q' => 'esp32'])).'"', false);
    }

    public function test_search_page_shows_a_helpful_empty_state(): void
    {
        $this->get('/search?q=xyzzyplugh')->assertOk()
            ->assertSee(__('site.listing.empty_title'))
            ->assertSee(__('site.listing.browse_all'));
    }

    public function test_suggestion_endpoint_returns_matching_products(): void
    {
        $this->getJson('/search/suggest?q=sensek')->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.name', 'SenseKit 6-Axis IMU Board')
            ->assertJsonStructure(['items' => [['name', 'sku', 'url', 'image', 'price']]]);

        $this->getJson('/search/suggest?q=esp32')->assertJsonPath('items.0.sku', 'ET-DEV-S3-01')->assertJsonPath('items.0.price', '$14.90');
        $this->getJson('/search/suggest?q=e')->assertOk()->assertExactJson(['items' => []]);
        $this->getJson('/search/suggest')->assertOk()->assertExactJson(['items' => []]);
    }
}
