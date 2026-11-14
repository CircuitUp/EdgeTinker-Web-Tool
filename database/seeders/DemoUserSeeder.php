<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\RevisionService;
use Illuminate\Database\Seeder;

/**
 * DEMO ONLY: one Content Creator with a known password, plus two sample
 * submissions so the review queue has something in it.
 *
 * Never run in production (DatabaseSeeder skips it there). The Content Admin
 * is not seeded: create it with `php artisan studio:create-admin`.
 */
class DemoUserSeeder extends Seeder
{
    public const EMAIL = 'creator@edgetinker.test';

    public const PASSWORD = 'Creator-Demo-123';

    public function run(RevisionService $revisions, ContentTypeRegistry $types): void
    {
        $creator = User::firstOrNew(['email' => self::EMAIL]);
        $creator->fill(['name' => 'Demo Creator', 'password' => self::PASSWORD]);
        $creator->status = UserStatus::Active;
        $creator->email_verified_at ??= now();
        $creator->save();

        if ($creator->revisions()->exists()) {
            return; // sample submissions already seeded
        }

        // 1. An edit to a live product, waiting for review.
        if ($product = Product::where('sku', 'ET-DEV-RP-01')->first()) {
            $payload = $types->get('product')->current($product->id);
            $payload['short_description'] = 'Thumb-sized RP2040 board with castellated edges, USB-C and an on-board RGB LED.';
            $payload['price_usd'] = '7.50';
            $payload['key_features'][] = 'Reset and boot buttons';
            $revisions->save($creator, 'product', $product->id, $payload, submit: true);
        }

        // 2. A brand-new product saved as a draft.
        if ($category = Category::where('slug', 'sensor-boards')->first()) {
            $revisions->save($creator, 'product', null, [
                'name' => 'SenseKit Current Sensor Board',
                'sku' => 'ET-SNS-CUR-01',
                'category_id' => $category->id,
                'short_description' => 'Bidirectional current and bus-voltage monitor with an I²C interface.',
                'description' => '<p>Measures current in both directions and bus voltage on rails up to 36 V. (Demo draft.)</p>',
                'key_features' => ['±3.2 A range', 'I²C interface', '0–36 V bus voltage'],
                'specs' => [['label' => 'Interface', 'value' => 'I²C'], ['label' => 'Bus voltage', 'value' => '0–36 V']],
                'price_usd' => '6.20', 'price_lkr' => '1900', 'stock_status' => 'coming_soon',
                'new_tags' => ['Current sensing'],
            ], submit: false);
        }
    }
}
