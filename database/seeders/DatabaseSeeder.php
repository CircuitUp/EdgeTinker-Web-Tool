<?php

namespace Database\Seeders;

use App\Support\SiteCache;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds demo content so every page can be reviewed with content in place.
     * Safe to re-run: seeders update existing rows instead of duplicating them.
     */
    public function run(): void
    {
        $this->call([
            DemoCatalogueSeeder::class,
            DemoWikiSeeder::class,
        ]);

        SiteCache::flush();

        // A demo login with a published password must never exist on the live site.
        if (! app()->isProduction()) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
