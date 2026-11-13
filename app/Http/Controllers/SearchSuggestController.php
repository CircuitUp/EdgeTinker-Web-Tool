<?php

namespace App\Http\Controllers;

use App\Search\ProductFilters;
use App\Search\ProductSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Instant suggestions shown under the header search bar while typing. */
class SearchSuggestController extends Controller
{
    private const LIMIT = 6;

    public function __invoke(Request $request, ProductSearch $search): JsonResponse
    {
        $term = $request->query('q');
        $term = is_string($term) ? mb_substr(trim($term), 0, ProductFilters::MAX_QUERY_LENGTH) : '';

        if (mb_strlen($term) < 2) {
            return response()->json(['items' => []]);
        }

        $code = currency()->code();

        $items = $search->suggestions($term, self::LIMIT)->map(fn ($product) => [
            'name' => $product->name,
            'sku' => $product->sku,
            'url' => route('products.show', $product->slug),
            'image' => $product->thumbnail?->url,
            'price' => currency()->format($product->priceIn($code)),
        ])->values();

        return response()->json(['items' => $items]);
    }
}
