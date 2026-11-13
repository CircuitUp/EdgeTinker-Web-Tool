<?php

namespace Database\Seeders;

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
        ]);
    }
}
