<?php

namespace App\Search;

use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Product search on MySQL / MariaDB.
 *
 * How a search term is matched
 * ----------------------------
 * The term is split into words. In STRICT mode every word must match the
 * product somewhere:
 *   - the FULLTEXT index (name, SKU, short description, description), as a word prefix
 *   - anywhere inside the name or SKU (LIKE), so partial names and SKU fragments work
 *   - a tag name or the category name
 *
 * FULLTEXT cannot tolerate typos, so when strict matching finds nothing
 * we fall back to LOOSE mode: any word may match, descriptions are
 * searched with LIKE, and long words are also tried with their ending trimmed
 * ("convertor" still finds "converter"). We also work out a "Did you mean …?"
 * suggestion by comparing the words with those used in product names, SKUs and tags.
 *
 * Results are ranked so that name matches come first, then SKU, tags and text.
 *
 * On other databases (SQLite in non-search tests) the FULLTEXT part is replaced
 * by LIKE so listings still work; ranking quality is only guaranteed on MySQL.
 */
class MySqlProductSearch implements ProductSearch
{
    public const VOCABULARY_CACHE_KEY = 'search.vocabulary';

    /** Fewer strict matches than this triggers the loose fallback and a suggestion. */
    private const FEW_RESULTS = 3;

    private const FULLTEXT_COLUMNS = 'products.name, products.sku, products.short_description, products.description';

    /** InnoDB ignores words shorter than this in FULLTEXT indexes (innodb_ft_min_token_size). */
    private const MIN_FULLTEXT_WORD = 3;

    public function search(ProductFilters $filters, int $perPage = 12): ProductSearchResults
    {
        $terms = $this->terms($filters->query);
        $loose = false;
        $didYouMean = null;

        if ($terms !== []) {
            $strictMatches = $this->textQuery($terms, loose: false)->count();

            // Nothing matched strictly: broaden the match rather than show an empty page.
            if ($strictMatches === 0) {
                $loose = $this->textQuery($terms, loose: true)->exists();
            }

            // Few or no matches: a word may be misspelt, so offer a correction.
            if ($strictMatches < self::FEW_RESULTS) {
                $didYouMean = $this->didYouMean($filters->query, $terms);
            }
        }

        // Scope = search term + category. Facets (price range) are measured on this,
        // so the slider always shows the full range available to the visitor.
        $scope = $this->textQuery($terms, $loose);
        if ($filters->category !== null) {
            $scope->whereHas('category', fn (Builder $category) => $category->where('slug', $filters->category));
        }

        $priceColumn = $filters->priceColumn();
        $bounds = (clone $scope)->whereNotNull("products.$priceColumn")
            ->selectRaw("MIN(products.$priceColumn) as low, MAX(products.$priceColumn) as high")
            ->toBase()->first();

        // Narrowing filters.
        $query = clone $scope;
        foreach ($filters->tags as $slug) {
            // Every selected tag must be present (selecting more tags narrows the results).
            $query->whereHas('tags', fn (Builder $tag) => $tag->where('tags.slug', $slug));
        }
        if ($filters->min !== null) {
            $query->where("products.$priceColumn", '>=', $filters->min);
        }
        if ($filters->max !== null) {
            $query->where("products.$priceColumn", '<=', $filters->max);
        }
        if ($filters->inStock) {
            $query->whereIn('products.stock_status', [StockStatus::InStock->value, StockStatus::LowStock->value]);
        }

        $tags = $this->tagFacets(clone $query);

        $query->select('products.*')->forCard();
        $this->applySort($query, $filters, $terms);

        return new ProductSearchResults(
            products: $query->paginate($perPage)->withQueryString(),
            loose: $loose,
            didYouMean: $didYouMean,
            tags: $tags,
            priceMin: $bounds?->low !== null ? (float) $bounds->low : null,
            priceMax: $bounds?->high !== null ? (float) $bounds->high : null,
        );
    }

    public function suggestions(string $term, int $limit = 6): Collection
    {
        $terms = $this->terms($term);
        if ($terms === []) {
            return collect();
        }

        foreach ([false, true] as $loose) {
            $query = $this->textQuery($terms, $loose)->select('products.*')->forCard();
            $this->applyRelevance($query, $term, $terms);
            $results = $query->orderByDesc('relevance')->orderBy('products.name')->limit($limit)->get();

            if ($results->isNotEmpty()) {
                return $results;
            }
        }

        return collect();
    }

    // ── Text matching ────────────────────────────────────────────────────────

    /**
     * Split a search phrase into words. Characters that are part of product
     * vocabulary are kept ("RS-485", "DC/DC", "ET-DEV-S3-01", "2.4GHz").
     *
     * @return list<string>
     */
    private function terms(string $phrase): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($phrase)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];
        foreach ($words as $word) {
            $word = trim(preg_replace('/[^\p{L}\p{N}\-\/\.\+]+/u', '', $word), '-/.+');
            if ($word !== '') {
                $terms[$word] = true;
            }
        }

        return array_slice(array_keys($terms), 0, 8);
    }

    /** Products matching the words (no category or other filters applied). */
    private function textQuery(array $terms, bool $loose): Builder
    {
        $query = Product::query();

        if ($terms === []) {
            return $query;
        }

        if ($loose) {
            // Any word may match.
            $query->where(function (Builder $any) use ($terms) {
                foreach ($terms as $term) {
                    $any->orWhere(fn (Builder $group) => $this->matchTerm($group, $term, loose: true));
                }
            });
        } else {
            // Every word must match.
            foreach ($terms as $term) {
                $query->where(fn (Builder $group) => $this->matchTerm($group, $term, loose: false));
            }
        }

        return $query;
    }

    private function matchTerm(Builder $group, string $term, bool $loose): void
    {
        $contains = '%'.$this->escapeLike($term).'%';

        $group->where('products.name', 'like', $contains)
            ->orWhere('products.sku', 'like', $contains)
            ->orWhereHas('tags', fn (Builder $tag) => $tag->where('tags.name', 'like', $contains))
            ->orWhereHas('category', fn (Builder $category) => $category->where('categories.name', 'like', $contains));

        $boolean = $this->booleanExpression($term);
        if ($boolean !== null) {
            $group->orWhereRaw('MATCH('.self::FULLTEXT_COLUMNS.') AGAINST (? IN BOOLEAN MODE)', [$boolean]);
        }

        if ($boolean === null || $loose) {
            $group->orWhere('products.short_description', 'like', $contains)
                ->orWhere('products.description', 'like', $contains);
        }

        // Typo tolerance for word endings: try the word without its last two letters.
        if ($loose && mb_strlen($term) >= 5) {
            $stem = '%'.$this->escapeLike(mb_substr($term, 0, -2)).'%';
            $group->orWhere('products.name', 'like', $stem)
                ->orWhereHas('tags', fn (Builder $tag) => $tag->where('tags.name', 'like', $stem));
        }
    }

    /**
     * FULLTEXT boolean-mode expression for one word, e.g. "esp32" → "+esp32*",
     * "rs-485" → "+485*". Returns null when FULLTEXT cannot be used for it
     * (not MySQL, or every part is shorter than the index's minimum word length).
     */
    private function booleanExpression(string $term): ?string
    {
        if (! $this->supportsFullText()) {
            return null;
        }

        // Only letters and digits reach the expression, so boolean operators cannot be injected.
        $parts = array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $part) => mb_strlen($part) >= self::MIN_FULLTEXT_WORD,
        );

        return $parts === [] ? null : implode(' ', array_map(fn (string $part) => "+$part*", $parts));
    }

    private function supportsFullText(): bool
    {
        return in_array(Product::query()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    // ── Ranking and sorting ──────────────────────────────────────────────────

    private function applySort(Builder $query, ProductFilters $filters, array $terms): void
    {
        $priceColumn = 'products.'.$filters->priceColumn();

        switch ($filters->effectiveSort()) {
            case 'relevance':
                $this->applyRelevance($query, $filters->query, $terms);
                $query->orderByDesc('relevance')->orderBy('products.name');
                break;
            case 'price_asc':
                // Products without a price in this currency go last.
                $query->orderByRaw("$priceColumn IS NULL")->orderBy($priceColumn);
                break;
            case 'price_desc':
                $query->orderByRaw("$priceColumn IS NULL")->orderByDesc($priceColumn);
                break;
            case 'name':
                $query->orderBy('products.name');
                break;
            default: // newest
                $query->orderByDesc('products.created_at');
        }

        $query->orderByDesc('products.id');
    }

    /**
     * Adds a `relevance` score column. Weights are chosen so that:
     * exact name > name starts with phrase > name contains phrase > SKU > per-word
     * name hits > tags > description text.
     */
    private function applyRelevance(Builder $query, string $phrase, array $terms): void
    {
        $phrase = mb_strtolower(trim($phrase));
        $like = $this->escapeLike($phrase);

        $sql = [];
        $bindings = [];

        $sql[] = 'CASE WHEN LOWER(products.name) = ? THEN 1000 WHEN products.name LIKE ? THEN 600 WHEN products.name LIKE ? THEN 400 ELSE 0 END';
        array_push($bindings, $phrase, "$like%", "%$like%");

        $sql[] = 'CASE WHEN LOWER(products.sku) = ? THEN 900 WHEN products.sku LIKE ? THEN 450 ELSE 0 END';
        array_push($bindings, $phrase, "%$like%");

        foreach ($terms as $term) {
            $contains = '%'.$this->escapeLike($term).'%';

            $sql[] = 'CASE WHEN products.name LIKE ? THEN 60 ELSE 0 END';
            $sql[] = 'CASE WHEN products.sku LIKE ? THEN 40 ELSE 0 END';
            $sql[] = 'CASE WHEN EXISTS (SELECT 1 FROM product_tag INNER JOIN tags ON tags.id = product_tag.tag_id'
                .' WHERE product_tag.product_id = products.id AND tags.name LIKE ?) THEN 30 ELSE 0 END';
            $sql[] = 'CASE WHEN EXISTS (SELECT 1 FROM categories WHERE categories.id = products.category_id'
                .' AND categories.name LIKE ?) THEN 20 ELSE 0 END';
            $sql[] = 'CASE WHEN products.short_description LIKE ? THEN 10 ELSE 0 END';
            array_push($bindings, $contains, $contains, $contains, $contains, $contains);
        }

        if ($this->supportsFullText() && $phrase !== '') {
            $sql[] = 'MATCH('.self::FULLTEXT_COLUMNS.') AGAINST (? IN NATURAL LANGUAGE MODE)';
            $bindings[] = $phrase;
        }

        $query->selectRaw('('.implode(' + ', $sql).') as relevance', $bindings);
    }

    // ── Facets ───────────────────────────────────────────────────────────────

    /** Tags carried by the products in the given result set, with counts. */
    private function tagFacets(Builder $products): Collection
    {
        return Tag::query()
            ->join('product_tag', 'product_tag.tag_id', '=', 'tags.id')
            ->whereIn('product_tag.product_id', $products->select('products.id'))
            ->groupBy('tags.id', 'tags.name', 'tags.slug')
            ->orderBy('tags.name')
            ->get(['tags.id', 'tags.name', 'tags.slug', DB::raw('COUNT(*) as products_count')]);
    }

    // ── "Did you mean …?" ────────────────────────────────────────────────────

    /**
     * Corrects each word that is not used by any product to the closest word
     * that is (by edit distance). Returns null when nothing needs correcting
     * or the corrected phrase would not find anything either.
     */
    private function didYouMean(string $phrase, array $terms): ?string
    {
        $vocabulary = $this->vocabulary();
        if ($vocabulary === []) {
            return null;
        }

        $known = array_flip($vocabulary);
        $corrected = [];
        $changed = false;

        foreach ($terms as $term) {
            if (isset($known[$term]) || $this->isPrefixOfKnownWord($term, $vocabulary)) {
                $corrected[] = $term;

                continue;
            }

            $best = $this->closestWord($term, $vocabulary);
            $corrected[] = $best ?? $term;
            $changed = $changed || $best !== null;
        }

        if (! $changed) {
            return null;
        }

        $suggestion = implode(' ', $corrected);
        if ($suggestion === mb_strtolower(trim($phrase))) {
            return null;
        }

        return $this->textQuery($this->terms($suggestion), loose: false)->exists() ? $suggestion : null;
    }

    private function isPrefixOfKnownWord(string $term, array $vocabulary): bool
    {
        if (mb_strlen($term) < 3) {
            return true; // too short to judge; leave it alone
        }

        foreach ($vocabulary as $word) {
            if (str_starts_with($word, $term)) {
                return true;
            }
        }

        return false;
    }

    private function closestWord(string $term, array $vocabulary): ?string
    {
        $length = strlen($term);
        if ($length < 3 || $length > 40) {
            return null;
        }

        // Allow more edits for longer words: 1 up to 4 letters, 2 up to 8, then 3.
        $maxDistance = $length <= 4 ? 1 : ($length <= 8 ? 2 : 3);

        $best = null;
        $bestDistance = $maxDistance + 1;
        $bestSimilarity = -1;

        foreach ($vocabulary as $word) {
            if (abs(strlen($word) - $length) > $maxDistance) {
                continue;
            }

            $distance = levenshtein($term, $word);
            if ($distance > $maxDistance) {
                continue;
            }

            similar_text($term, $word, $similarity);
            if ($distance < $bestDistance || ($distance === $bestDistance && $similarity > $bestSimilarity)) {
                [$best, $bestDistance, $bestSimilarity] = [$word, $distance, $similarity];
            }
        }

        return $best;
    }

    /**
     * Lower-case words used in product names, SKUs, tags and category names. Cached; the cache
     * is cleared whenever a product or tag changes (see the models).
     *
     * @return list<string>
     */
    private function vocabulary(): array
    {
        return Cache::remember(self::VOCABULARY_CACHE_KEY, now()->addHours(6), function () {
            $words = [];

            $add = function (string $text) use (&$words) {
                $text = mb_strtolower(trim($text));
                if ($text === '') {
                    return;
                }
                // The whole token ("usb-c", "et-dev-s3-01") and its parts ("dev").
                foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                    $token = trim($token, '-/.+,()');
                    if (mb_strlen($token) >= 3) {
                        $words[$token] = true;
                    }
                    foreach (preg_split('/[^\p{L}\p{N}]+/u', $token, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                        if (mb_strlen($part) >= 3) {
                            $words[$part] = true;
                        }
                    }
                }
            };

            Product::query()->toBase()->select('name', 'sku')->orderBy('id')->each(function ($row) use ($add) {
                $add($row->name);
                $add($row->sku);
            });
            Tag::query()->toBase()->select('name')->orderBy('id')->each(fn ($row) => $add($row->name));
            Category::query()->toBase()->select('name')->orderBy('id')->each(fn ($row) => $add($row->name));

            return array_map('strval', array_keys($words));
        });
    }
}
