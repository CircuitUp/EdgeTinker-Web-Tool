<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Product;
use App\Support\PlaceholderArt;
use App\Support\SiteCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * DEMO ONLY: six placeholder partner logos for the home page, and a placeholder
 * store link on each demo product so the "Buy" button can be seen.
 *
 * It never overwrites real content: partners are added only when the home
 * page has none, and store links only where a demo product has none.
 * Replace both in Content Studio (Pages → Home, and each product's form).
 */
class DemoPartnersSeeder extends Seeder
{
    private const PARTNER_COUNT = 6;

    /** The products created by DemoCatalogueSeeder. */
    private const DEMO_SKUS = [
        'ET-DEV-S3-01', 'ET-DEV-G4-01', 'ET-DEV-RP-01', 'ET-PWR-BK-0503', 'ET-PWR-BB-ADJ', 'ET-PWR-ISO-2405',
        'ET-CHG-1S-C', 'ET-CHG-MPPT-01', 'ET-COM-LORA-868', 'ET-COM-RS485-ISO', 'ET-SNS-ENV-01', 'ET-SNS-IMU-6X',
    ];

    public function run(): void
    {
        File::ensureDirectoryExists(public_path('placeholders'));

        $partners = [];
        for ($number = 1; $number <= self::PARTNER_COUNT; $number++) {
            $path = "placeholders/partner-$number.svg";
            File::put(public_path($path), PlaceholderArt::partnerLogo($number));
            $partners[] = ['name' => "Partner $number (placeholder)", 'logo' => ['media_id' => null, 'path' => $path]];
        }

        $home = Page::firstOrNew(['slug' => 'home']);
        $content = $home->content ?? [];
        if (empty($content['partners'])) {
            $home->content = ['partners' => $partners] + $content;
            $home->save();
        }

        // Only the twelve demo products, never a product someone added themselves.
        // example.com is reserved for examples, so these links are obviously not a real store.
        Product::query()->whereIn('sku', self::DEMO_SKUS)->whereNull('store_url')->get()
            ->each(fn (Product $product) => $product->update(['store_url' => 'https://example.com/store/'.$product->slug]));

        SiteCache::flush();
    }
}
