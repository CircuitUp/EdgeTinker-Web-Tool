<?php

namespace App\Support;

use App\Models\Category;
use App\Search\MySqlProductSearch;
use Illuminate\Support\Facades\Cache;

/**
 * Cached pieces of the public site. Everything cached for visitors is listed
 * here so that approving a change can clear it in one call, without flushing
 * the whole cache store (which also holds login rate limits).
 */
class SiteCache
{
    public const KEYS = [
        Category::NAV_CACHE_KEY,
        MySqlProductSearch::VOCABULARY_CACHE_KEY,
        Settings::CACHE_KEY,
        'home.data',
        'pages.content',
        'wiki.tree',
        'sitemap.xml',
    ];

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }

        app(Settings::class)->flush();
    }
}
