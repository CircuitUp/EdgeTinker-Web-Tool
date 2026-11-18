<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Builds the favicon set in public/ from the EdgeTinker logo mark.
 *
 * Source: resources/brand/edgetinker-mark.png (square, white mark on purple,
 * at least 512 px). If resources/brand/edgetinker-mark.svg exists it is also
 * published as favicon.svg. Re-run after changing the logo:
 *
 *     php artisan brand:favicons
 *
 * Small icons (16–48 px) crop some of the logo's padding so the mark stays
 * legible, and get rounded corners. The Apple touch icon is a full square
 * (iOS rounds it itself). Android gets rounded icons plus a full-square
 * "maskable" one. Uses GD only (no extra packages).
 */
class GenerateFavicons extends Command
{
    protected $signature = 'brand:favicons {--source=resources/brand/edgetinker-mark.png : Square PNG of the logo mark}';

    protected $description = 'Generate favicon.ico, PNG icons and site.webmanifest from the logo';

    public const THEME_COLOR = '#2E1065';

    public const BACKGROUND_COLOR = '#3B2188';

    public function handle(): int
    {
        $source = base_path($this->option('source'));
        if (! is_file($source)) {
            $this->error("Logo not found: $source");

            return self::FAILURE;
        }

        $logo = imagecreatefrompng($source);
        if (! $logo || imagesx($logo) !== imagesy($logo) || imagesx($logo) < 512) {
            $this->error('The logo must be a square PNG of at least 512 × 512 px.');

            return self::FAILURE;
        }

        $files = [];
        $small = [];
        foreach ([16, 32, 48] as $size) {
            // Small sizes: crop 9 % of padding on each side so the mark is larger, then round the corners.
            $small[$size] = $this->png($this->render($logo, $size, crop: 0.09, radius: 0.22));
        }
        $files['favicon-16x16.png'] = $small[16];
        $files['favicon-32x32.png'] = $small[32];
        $files['favicon.ico'] = $this->ico($small);
        $files['apple-touch-icon.png'] = $this->png($this->render($logo, 180, crop: 0, radius: 0));
        $files['android-chrome-192x192.png'] = $this->png($this->render($logo, 192, crop: 0, radius: 0.18));
        $files['android-chrome-512x512.png'] = $this->png($this->render($logo, 512, crop: 0, radius: 0.18));
        $files['android-chrome-maskable-512x512.png'] = $this->png($this->render($logo, 512, crop: 0, radius: 0));
        $files['site.webmanifest'] = json_encode($this->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

        $svg = base_path('resources/brand/edgetinker-mark.svg');
        if (is_file($svg)) {
            $files['favicon.svg'] = file_get_contents($svg);
        }

        foreach ($files as $name => $data) {
            file_put_contents(public_path($name), $data);
            $this->line("  public/$name (".number_format(strlen($data)).' bytes)');
        }

        $this->info('Favicons generated.');

        return self::SUCCESS;
    }

    /** The logo at $size px, optionally cropped by $crop (share of each side) and with corners rounded by $radius (share of the size). */
    private function render(\GdImage $logo, int $size, float $crop, float $radius): \GdImage
    {
        $src = imagesx($logo);
        $cut = (int) round($src * $crop);
        $region = $src - 2 * $cut;

        // Shrink in halves first: a single big step from 1000 px to 16 px loses the thin parts of the mark.
        $work = $logo;
        $workSize = $region;
        $offset = $cut;
        while ($workSize / 2 >= $size * 2) {
            $half = (int) floor($workSize / 2);
            $next = $this->canvas($half);
            imagecopyresampled($next, $work, 0, 0, $offset, $offset, $half, $half, $workSize, $workSize);
            [$work, $workSize, $offset] = [$next, $half, 0];
        }

        $out = $this->canvas($size);
        imagecopyresampled($out, $work, 0, 0, $offset, $offset, $size, $size, $workSize, $workSize);

        if ($radius > 0) {
            $this->roundCorners($out, $size, $radius * $size);
        }

        return $out;
    }

    private function canvas(int $size): \GdImage
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        return $image;
    }

    /** Anti-aliased rounded corners: each corner pixel's opacity follows how much of it lies inside the curve. */
    private function roundCorners(\GdImage $image, int $size, float $r): void
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $cx = $x < $r ? $r : ($x >= $size - $r ? $size - $r : null);
                $cy = $y < $r ? $r : ($y >= $size - $r ? $size - $r : null);
                if ($cx === null || $cy === null) {
                    continue;
                }

                // Coverage from 4 × 4 samples inside the pixel.
                $inside = 0;
                for ($sy = 0; $sy < 4; $sy++) {
                    for ($sx = 0; $sx < 4; $sx++) {
                        $inside += hypot($x + ($sx + 0.5) / 4 - $cx, $y + ($sy + 0.5) / 4 - $cy) <= $r ? 1 : 0;
                    }
                }
                if ($inside === 16) {
                    continue;
                }

                $rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $alpha = 127 - (int) round((127 - $rgba['alpha']) * $inside / 16);
                imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $rgba['red'], $rgba['green'], $rgba['blue'], $alpha));
            }
        }
    }

    private function png(\GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    /** A .ico holding PNG images (supported by every current browser and by Windows Vista and later). */
    private function ico(array $pngs): string
    {
        $header = pack('vvv', 0, 1, count($pngs));
        $directory = '';
        $data = '';
        $offset = 6 + 16 * count($pngs);

        foreach ($pngs as $size => $png) {
            if ($size > 256) {
                throw new RuntimeException('ICO images are at most 256 px.');
            }
            $directory .= pack('CCCCvvVV', $size === 256 ? 0 : $size, $size === 256 ? 0 : $size, 0, 0, 1, 32, strlen($png), $offset);
            $data .= $png;
            $offset += strlen($png);
        }

        return $header.$directory.$data;
    }

    private function manifest(): array
    {
        return [
            'name' => 'EdgeTinker',
            'short_name' => 'EdgeTinker',
            'description' => 'Embedded hardware, engineered to build on.',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'browser',
            'theme_color' => self::THEME_COLOR,
            'background_color' => self::BACKGROUND_COLOR,
            'icons' => [
                ['src' => '/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/android-chrome-maskable-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
    }
}
