<?php

namespace App\Support;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Support\Banner\BannerConfig;
use App\Support\Banner\BannerPresenter;
use Illuminate\Support\Facades\Cache;

/** Gathers everything the home page shows. Also used to preview a pending change to the page. */
class HomePage
{
    public const BANNER_CACHE_KEY = 'home.data';

    /**
     * @param  array|null  $override  page content from a pending revision (for the Content Studio
     *                                preview); null renders what is live
     * @param  array|null  $banner  a banner config from a pending revision or the banner editor; null = the live banner
     */
    public static function data(?array $override = null, ?array $banner = null): array
    {
        $pages = app(PageContent::class);
        $content = $override === null ? $pages->get('home') : array_replace(PageContent::defaults('home'), $override);

        return [
            'content' => $content,
            'banner' => $banner === null ? self::liveBanner() : app(BannerPresenter::class)->present(BannerConfig::normalise($banner)),
            'categories' => Category::navigation(),
            'featured' => self::featured($override === null ? null : ($content['featured_product_ids'] ?? [])),
            'services' => $pages->get('services')['services'],
            'partners' => self::partners($content['partners'] ?? []),
            'seo' => $override === null ? $pages->seo('home') : ['title' => null, 'description' => null],
        ];
    }

    /**
     * The live banner, ready for the template and cached. It only changes when
     * a banner revision is approved, which clears this cache (SiteCache::flush()).
     */
    private static function liveBanner(): array
    {
        return Cache::remember(self::BANNER_CACHE_KEY, now()->addHours(6),
            fn () => app(BannerPresenter::class)->present(Banner::home()->design()));
    }

    /** Featured products, in the order chosen on the Home page editor (others follow, newest first). */
    private static function featured(?array $previewIds)
    {
        $limit = config('edgetinker.home.featured_limit');

        if ($previewIds !== null) {
            $products = Product::forCard()->whereIn('id', $previewIds)->get();
            $order = array_flip($previewIds);
        } else {
            $products = Product::featured()->forCard()->latest()->get();
            $order = array_flip(app(PageContent::class)->get('home')['featured_product_ids'] ?? []);
        }

        return $products->sortBy(fn (Product $product) => $order[$product->id] ?? PHP_INT_MAX)->take($limit)->values();
    }

    /**
     * Partner logos ready for the template: name (used as the image's text
     * alternative), src, and the logo's real proportions when known.
     * Partners whose logo file is missing are left out.
     *
     * @return list<array{name: string, src: string, width: int, height: int}>
     */
    private static function partners(array $rows): array
    {
        $media = Media::whereIn('id', collect($rows)->pluck('logo.media_id')->filter())->get()->keyBy('id');
        $partners = [];

        foreach ($rows as $row) {
            $file = ($row['logo']['media_id'] ?? null) ? ($media[$row['logo']['media_id']] ?? null) : null;
            $src = $file ? $file->variantUrl(400) : media_url($row['logo']['path'] ?? null);

            if (! $src) {
                continue;
            }

            // Displayed 48 px high; the width follows the logo's own proportions.
            $ratio = $file && $file->width && $file->height ? $file->width / $file->height : 10 / 3;

            $partners[] = ['name' => (string) ($row['name'] ?? ''), 'src' => $src, 'width' => (int) round(48 * $ratio), 'height' => 48];
        }

        return $partners;
    }
}
