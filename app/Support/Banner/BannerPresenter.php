<?php

namespace App\Support\Banner;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Prepares a BannerConfig for the <x-banner.hero> component: resolves image
 * and video URLs, builds every inline style and lists the fonts to load.
 * The result is plain arrays, so it can be cached.
 */
class BannerPresenter
{
    public function __construct(private readonly FontInstaller $fonts) {}

    public function present(array $config): array
    {
        $config = BannerConfig::normalise($config);
        $ids = collect($config['slides'])->flatMap(fn ($s) => [$s['background']['image']['media_id'], $s['background']['video']['media_id']])->filter()->unique();
        $media = Media::whereIn('id', $ids)->get()->keyBy('id');

        $slides = [];
        foreach ($config['slides'] as $slide) {
            $bg = $slide['background'];
            $image = $bg['image']['media_id'] ? ($media[$bg['image']['media_id']] ?? null) : null;
            $video = $bg['video']['media_id'] ? ($media[$bg['video']['media_id']] ?? null) : null;

            $elements = [];
            foreach ($slide['elements'] as $layer => $el) {
                if ($el['visible'] && $el['content'] !== '') {
                    $elements[] = ['el' => $el, 'style' => BannerStyles::element($el, $layer)];
                }
            }

            $slides[] = [
                'id' => $slide['id'],
                'style' => BannerStyles::background($slide).';'.BannerStyles::overlay($slide),
                'type' => $bg['type'] === 'video' && $video ? 'video' : ($bg['type'] === 'color' ? 'color' : 'image'),
                'image' => $bg['type'] === 'image' ? $this->image($image, $bg['image']['path']) : null,
                'video' => $video?->url,
                'alt' => $bg['alt'],
                'elements' => $elements,
            ];
        }

        return [
            'style' => BannerStyles::banner($config),
            'frame' => $config['frame'],
            'scrim' => $config['scrim'],
            'slides' => $slides,
            'fonts' => $this->fonts->assets($config),
        ];
    }

    /** src, srcset (resized WebP versions plus the original) and size of a background image. */
    private function image(?Media $media, ?string $path): ?array
    {
        if ($media) {
            $srcset = collect($media->variants ?? [])->map(fn (string $p, $w) => Storage::disk('public')->url($p)." {$w}w");
            if ($media->width) {
                $srcset->push($media->url." {$media->width}w");
            }

            return ['src' => $media->url, 'srcset' => $srcset->isNotEmpty() ? $srcset->implode(', ') : null,
                'width' => $media->width ?: 1920, 'height' => $media->height ?: 900];
        }

        $src = media_url($path);

        return $src ? ['src' => $src, 'srcset' => null, 'width' => 1920, 'height' => 900] : null;
    }
}
