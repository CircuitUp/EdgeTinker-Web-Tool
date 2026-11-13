<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Support\Str;

/** Small helpers for creating catalogue data in tests. */
trait BuildsCatalogue
{
    private int $catalogueCounter = 0;

    protected function makeCategory(string $name, array $overrides = []): Category
    {
        return Category::create($overrides + [
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => Category::count() + 1,
        ]);
    }

    /** @param list<string> $tags tag names (created on first use) */
    protected function makeProduct(Category $category, string $name, array $overrides = [], array $tags = []): Product
    {
        $n = ++$this->catalogueCounter;

        $product = Product::create($overrides + [
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.$n,
            'sku' => 'ET-T-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'short_description' => 'Short description.',
            'description' => '<p>Long description.</p>',
            'price_usd' => 10,
            'price_lkr' => 3000,
            'stock_status' => 'in_stock',
        ]);

        if ($tags !== []) {
            $product->tags()->sync(collect($tags)->map(
                fn (string $tag) => Tag::firstOrCreate(['slug' => Str::slug($tag)], ['name' => $tag])->id
            ));
        }

        return $product;
    }
}
