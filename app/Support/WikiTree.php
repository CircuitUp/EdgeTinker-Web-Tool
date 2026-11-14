<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * The wiki's navigation tree: category → product → pages. It is derived from
 * the catalogue (only products that have at least one wiki page appear) and
 * cached; SiteCache::flush() clears it whenever a change is approved.
 */
class WikiTree
{
    public const CACHE_KEY = 'wiki.tree';

    /** @return list<array{name: string, slug: string, products: list<array{name: string, slug: string, pages: list<array{title: string, slug: string}>}>}> */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(6), function () {
            return Category::query()
                ->with(['products' => fn ($query) => $query->whereHas('wikiPages')->orderBy('name')->with('wikiPages:id,product_id,title,slug,sort_order')])
                ->orderBy('sort_order')->orderBy('name')->get()
                ->filter(fn (Category $category) => $category->products->isNotEmpty())
                ->map(fn (Category $category) => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'products' => $category->products->map(fn ($product) => [
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'pages' => $product->wikiPages->map(fn ($page) => ['title' => $page->title, 'slug' => $page->slug])->all(),
                    ])->all(),
                ])->values()->all();
        });
    }
}
