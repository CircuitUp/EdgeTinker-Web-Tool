<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\RichText;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    /** Dedicated product page: /product/{slug} */
    public function show(string $slug): View
    {
        $product = Product::query()
            ->with(['category', 'images', 'specs', 'tags'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Other products from the same category, newest first.
        $related = Product::query()
            ->forCard()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->latest()
            ->orderBy('id')
            ->limit(4)
            ->get();

        return view('products.show', [
            'product' => $product,
            'related' => $related,
            'description' => RichText::clean($product->description),
            'structuredData' => $this->structuredData($product),
        ]);
    }

    /** schema.org Product markup so search engines can show price and availability. */
    private function structuredData(Product $product): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->short_description,
            'category' => $product->category->name,
            'brand' => ['@type' => 'Brand', 'name' => setting('site.name')],
            'url' => route('products.show', $product->slug),
            'image' => $product->images->map(fn ($image) => $image->url)->values()->all(),
        ];

        // Prices are entered by hand per currency; list whichever ones exist.
        $offers = [];
        foreach (['USD' => $product->price_usd, 'LKR' => $product->price_lkr] as $code => $price) {
            if ($price !== null) {
                $offers[] = [
                    '@type' => 'Offer',
                    'price' => number_format((float) $price, 2, '.', ''),
                    'priceCurrency' => $code,
                    'availability' => $product->stock_status->schemaOrg(),
                    'url' => route('products.show', $product->slug),
                ];
            }
        }
        if ($offers) {
            $data['offers'] = count($offers) === 1 ? $offers[0] : $offers;
        }

        return $data;
    }
}
