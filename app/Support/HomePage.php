<?php

namespace App\Support;

use App\Models\BannerSlide;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/** Gathers everything the home page shows. Also used to preview a pending change to the page. */
class HomePage
{
    public const SLIDES_CACHE_KEY = 'home.data';

    /**
     * @param  array|null  $override  page content from a pending revision (for the Content Studio
     *                                preview); null renders what is live
     */
    public static function data(?array $override = null): array
    {
        $pages = app(PageContent::class);
        $content = $override === null ? $pages->get('home') : array_replace(PageContent::defaults('home'), $override);

        return [
            'content' => $content,
            'slides' => $override === null ? self::liveSlides() : self::slidesFrom($content['slides'] ?? []),
            'categories' => Category::navigation(),
            'featured' => self::featured($override === null ? null : ($content['featured_product_ids'] ?? [])),
            'services' => $pages->get('services')['services'],
            'partners' => self::partners($content['partners'] ?? []),
            'seo' => $override === null ? $pages->seo('home') : ['title' => null, 'description' => null],
        ];
    }

    /**
     * The live slides, cached as plain rows. Slides only change when a Home
     * page revision is approved, which clears this cache (SiteCache::flush()).
     */
    private static function liveSlides()
    {
        return BannerSlide::hydrate(Cache::remember(self::SLIDES_CACHE_KEY, now()->addHours(6),
            fn () => BannerSlide::active()->get()->map(fn (BannerSlide $slide) => $slide->getAttributes())->all()));
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

    /** Unsaved slide models built from a revision payload. */
    private static function slidesFrom(array $rows)
    {
        $media = Media::whereIn('id', collect($rows)->pluck('image.media_id')->filter())->get()->keyBy('id');

        return collect($rows)->map(fn (array $row) => new BannerSlide([
            'heading' => $row['heading'] ?? '',
            'sub_text' => $row['sub_text'] ?? null,
            'button_label' => $row['button_label'] ?? null,
            'button_url' => $row['button_url'] ?? null,
            'image_path' => ($row['image']['media_id'] ?? null) ? ($media[$row['image']['media_id']]->path ?? null) : ($row['image']['path'] ?? null),
            'image_alt' => $row['image_alt'] ?? '',
        ]));
    }
}
