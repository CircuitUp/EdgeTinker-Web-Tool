<?php

namespace App\Search;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Product search and listing.
 *
 * The site only talks to this interface. The current implementation uses
 * MySQL FULLTEXT (MySqlProductSearch); a dedicated engine (Meilisearch,
 * Typesense, …) can be swapped in later by binding another implementation
 * in AppServiceProvider, without touching controllers or views.
 */
interface ProductSearch
{
    /** A filtered, sorted, paginated product listing with facet data. */
    public function search(ProductFilters $filters, int $perPage = 12): ProductSearchResults;

    /**
     * A handful of best matches for the instant suggestions under the search bar.
     *
     * @return Collection<int, Product>
     */
    public function suggestions(string $term, int $limit = 6): Collection;
}
