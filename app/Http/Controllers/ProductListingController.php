<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tag;
use App\Search\ProductFilters;
use App\Search\ProductSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * One shared listing layout serves three pages:
 *   /products              all categories
 *   /products/{category}   one category
 *   /search?q=…            search results
 * All filters live in the URL, so any result page can be shared or bookmarked.
 */
class ProductListingController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(private readonly ProductSearch $search) {}

    public function index(Request $request): View
    {
        return $this->listing($request, 'index');
    }

    public function category(Request $request, Category $category): View
    {
        return $this->listing($request, 'category', $category);
    }

    public function search(Request $request): View
    {
        // On the search page the category is an ordinary filter (?category=slug).
        $slug = $request->query('category');
        $category = is_string($slug) ? Category::query()->where('slug', $slug)->first() : null;

        return $this->listing($request, 'search', $category);
    }

    private function listing(Request $request, string $page, ?Category $category = null): View
    {
        $filters = ProductFilters::fromRequest($request, currency()->code(), $category?->slug);

        // The search term only applies on the search page.
        if ($page !== 'search' && $filters->hasQuery()) {
            $filters = new ProductFilters(
                category: $filters->category, tags: $filters->tags, min: $filters->min, max: $filters->max,
                inStock: $filters->inStock, sort: $filters->sort, currency: $filters->currency,
            );
        }

        $results = $this->search->search($filters, self::PER_PAGE);

        return view('products.index', [
            'page' => $page,
            'category' => $category,
            'categories' => Category::navigation(),
            'filters' => $filters,
            'results' => $results,
            'products' => $results->products,
            // Names for the "active filter" chips (selected tags may not be in the facet list).
            'selectedTags' => $filters->tags === []
                ? collect()
                : Tag::query()->whereIn('slug', $filters->tags)->orderBy('name')->get(['name', 'slug']),
            'slider' => $this->slider($results->priceMin, $results->priceMax, $filters),
        ]);
    }

    /**
     * Price slider settings in the selected currency, or null when the products
     * in scope do not span a range worth filtering.
     */
    private function slider(?float $low, ?float $high, ProductFilters $filters): ?array
    {
        if ($low === null || $high === null) {
            return null;
        }

        // Round the ends outwards to tidy steps: whole dollars, or 50 rupees.
        $step = $filters->currency === 'LKR' ? 50 : 1;
        $min = floor($low / $step) * $step;
        $max = ceil($high / $step) * $step;

        if ($max <= $min) {
            return null;
        }

        $clamp = fn (?float $value, float $default) => $value === null ? $default : min($max, max($min, $value));
        $from = $clamp($filters->min, $min);
        $to = max($from, $clamp($filters->max, $max));

        return [
            'min' => $min, 'max' => $max, 'step' => $step, 'from' => $from, 'to' => $to,
            'prefix' => $filters->currency === 'LKR' ? 'Rs ' : '$',
        ];
    }
}
