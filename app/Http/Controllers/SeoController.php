<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\WikiPage;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** /sitemap.xml and /robots.txt */
class SeoController extends Controller
{
    public const SITEMAP_CACHE_KEY = 'sitemap.xml';

    /**
     * Every public, indexable page. Content Studio, search results, filtered
     * listings and the styleguide are deliberately absent.
     * Cached; SiteCache::flush() clears it whenever a change is approved.
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember(self::SITEMAP_CACHE_KEY, now()->addHours(12), function () {
            $pageDates = Page::pluck('updated_at', 'slug');
            $urls = [
                [route('home'), $pageDates['home'] ?? null, '1.0'],
                [route('products.index'), Product::max('updated_at'), '0.9'],
                [route('services'), $pageDates['services'] ?? null, '0.7'],
                [route('contact'), $pageDates['contact'] ?? null, '0.5'],
            ];

            foreach (Category::orderBy('sort_order')->get(['slug', 'updated_at']) as $category) {
                $urls[] = [route('products.category', $category->slug), $category->updated_at, '0.8'];
            }

            foreach (Product::orderBy('id')->get(['slug', 'updated_at']) as $product) {
                $urls[] = [route('products.show', $product->slug), $product->updated_at, '0.8'];
            }

            $wikiPages = WikiPage::with('product.category:id,slug')->orderBy('product_id')->orderBy('sort_order')->get();
            if ($wikiPages->isNotEmpty()) {
                $urls[] = [route('wiki.index'), $wikiPages->max('updated_at'), '0.6'];
            }
            foreach ($wikiPages as $page) {
                if ($page->product?->category) {
                    $urls[] = [$page->url(), $page->updated_at, '0.6'];
                }
            }

            $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
            foreach ($urls as [$location, $modified, $priority]) {
                $lines[] = '  <url><loc>'.htmlspecialchars($location, ENT_XML1).'</loc>'
                    .($modified ? '<lastmod>'.Carbon::parse($modified)->toDateString().'</lastmod>' : '')
                    ."<priority>$priority</priority></url>";
            }
            $lines[] = '</urlset>';

            return implode("\n", $lines)."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Content Studio is NOT listed here on purpose: robots.txt is public, so
     * naming the address would advertise it. It is kept out of search engines
     * by `noindex` headers on every one of its responses instead.
     */
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow:', '', 'Sitemap: '.route('sitemap')]
            // Local and staging copies must never be indexed.
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
