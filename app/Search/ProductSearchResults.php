<?php

namespace App\Search;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ProductSearchResults
{
    /**
     * @param  LengthAwarePaginator  $products  the page of products (loaded for product cards)
     * @param  bool  $loose  true when the strict match found too little and broader matching was used
     * @param  string|null  $didYouMean  a corrected search term worth offering, if any
     * @param  Collection  $tags  tags present in the current results: objects with name, slug, products_count
     * @param  float|null  $priceMin  lowest price in scope (selected currency), before the price filter
     * @param  float|null  $priceMax  highest price in scope
     */
    public function __construct(
        public readonly LengthAwarePaginator $products,
        public readonly bool $loose,
        public readonly ?string $didYouMean,
        public readonly Collection $tags,
        public readonly ?float $priceMin,
        public readonly ?float $priceMax,
    ) {}
}
