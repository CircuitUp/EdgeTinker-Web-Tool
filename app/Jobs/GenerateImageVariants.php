<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Creates WebP versions of an uploaded image at the standard widths, for
 * thumbnails and responsive `srcset`. Runs on the queue so uploads stay fast.
 * Uses Imagick when the server has it, otherwise GD (Hostinger offers both).
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $mediaId) {}

    /** Imagick when the server has it, otherwise GD. */
    public static function imageManager(): ImageManager
    {
        return ImageManager::usingDriver(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class);
    }

    public function handle(): void
    {
        $media = Media::find($this->mediaId);
        $disk = Storage::disk('public');

        if (! $media || ! $media->isImage() || ! $disk->exists($media->path)) {
            return;
        }

        $manager = self::imageManager();
        $base = preg_replace('/\.[^.]+$/', '', $media->path);
        $variants = [];

        foreach (Media::VARIANT_WIDTHS as $width) {
            // Never enlarge: skip widths bigger than the original (but always make the smallest).
            if ($media->width && $width > $media->width && $variants !== []) {
                continue;
            }

            $target = "$base-$width.webp";
            $manager->decodePath($disk->path($media->path))
                ->orient()                      // honour the camera's rotation flag
                ->scaleDown(width: $width)
                ->save($disk->path($target), quality: 82);

            $variants[(string) $width] = $target;
        }

        ksort($variants, SORT_NUMERIC);
        $media->update(['variants' => $variants]);
    }
}
