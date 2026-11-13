<?php

namespace App\Search;

use App\Support\Currency;
use Illuminate\Http\Request;

/**
 * Everything a visitor can ask of a product listing, read from the URL:
 * ?q=…&category=…&tags=a,b&min=…&max=…&in_stock=1&sort=…
 *
 * Immutable, so it is safe to pass around and to derive URLs from.
 */
final class ProductFilters
{
    public const SORTS = ['relevance', 'newest', 'price_asc', 'price_desc', 'name'];

    public const MAX_QUERY_LENGTH = 100;

    private const MAX_TAGS = 10;

    /** @param list<string> $tags tag slugs */
    public function __construct(
        public readonly string $query = '',
        public readonly ?string $category = null,
        public readonly array $tags = [],
        public readonly ?float $min = null,
        public readonly ?float $max = null,
        public readonly bool $inStock = false,
        public readonly ?string $sort = null,
        public readonly string $currency = Currency::DEFAULT,
    ) {}

    /**
     * @param  string|null  $category  category slug fixed by the page (e.g. /products/{category});
     *                                 when null it is read from ?category=
     */
    public static function fromRequest(Request $request, string $currency, ?string $category = null): self
    {
        $query = $request->query('q');
        $query = is_string($query) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $query)), 0, self::MAX_QUERY_LENGTH) : '';

        $category ??= self::slug($request->query('category'));

        $sort = $request->query('sort');

        return new self(
            query: $query,
            category: $category,
            tags: self::tags($request->query('tags')),
            min: self::price($request->query('min')),
            max: self::price($request->query('max')),
            inStock: $request->boolean('in_stock'),
            sort: is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : null,
            currency: Currency::isSupported($currency) ? strtoupper($currency) : Currency::DEFAULT,
        );
    }

    public function hasQuery(): bool
    {
        return $this->query !== '';
    }

    /** Products column holding the price in the visitor's currency. */
    public function priceColumn(): string
    {
        return $this->currency === 'LKR' ? 'price_lkr' : 'price_usd';
    }

    /** The sort actually applied: relevance only makes sense with a search term. */
    public function effectiveSort(): string
    {
        if ($this->sort === null || ($this->sort === 'relevance' && ! $this->hasQuery())) {
            return $this->hasQuery() ? 'relevance' : 'newest';
        }

        return $this->sort;
    }

    /** True when any narrowing filter (not the search term or sort) is active. */
    public function hasNarrowingFilters(): bool
    {
        return $this->tags !== [] || $this->min !== null || $this->max !== null || $this->inStock;
    }

    /**
     * Query-string parameters for a link that keeps these filters, with overrides.
     * Pass null for a key to drop it. Tags are written as a comma-separated list.
     */
    public function toQuery(array $overrides = []): array
    {
        $params = array_merge([
            'q' => $this->query,
            'category' => $this->category,
            'tags' => $this->tags,
            'min' => $this->min,
            'max' => $this->max,
            'in_stock' => $this->inStock ? 1 : null,
            'sort' => $this->sort,
        ], $overrides);

        if (is_array($params['tags'] ?? null)) {
            $params['tags'] = implode(',', $params['tags']);
        }

        foreach (['min', 'max'] as $key) {
            if (is_float($params[$key] ?? null)) {
                // 25.0 → "25", 12.5 → "12.5"
                $params[$key] = rtrim(rtrim(number_format($params[$key], 2, '.', ''), '0'), '.');
            }
        }

        return array_filter($params, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /** Accepts "a,b", ["a", "b"] or tags[]=a&tags[]=b. */
    private static function tags(mixed $value): array
    {
        $items = is_array($value) ? $value : (is_string($value) ? explode(',', $value) : []);

        $slugs = [];
        foreach ($items as $item) {
            $slug = self::slug($item);
            if ($slug !== null) {
                $slugs[$slug] = true;
            }
        }

        return array_slice(array_keys($slugs), 0, self::MAX_TAGS);
    }

    private static function slug(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) && strlen($value) <= 100 ? $value : null;
    }

    private static function price(mixed $value): ?float
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        return is_numeric($value) && $value >= 0 && $value <= 100_000_000 ? (float) $value : null;
    }
}
