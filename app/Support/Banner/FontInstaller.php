<?php

namespace App\Support\Banner;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Self-hosts the Google Fonts used by the banner.
 *
 * The first time an editor picks a Google font (family + weight + style),
 * this server downloads the Latin and Latin Extended WOFF2 files from Google
 * Fonts into storage/app/public/banner-fonts/{family}/ and writes a font.css
 * with @font-face rules (font-display: swap). The live site then links that
 * CSS and preloads the Latin files, so visitors never contact Google and the
 * site's Content-Security-Policy (fonts from this site only) is unchanged.
 */
class FontInstaller
{
    private const DIR = 'banner-fonts';

    private const SUBSETS = ['latin', 'latin-ext'];

    /** Google serves WOFF2 only to browsers it recognises. */
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    /** Make sure one face is available locally. Returns false if it could not be downloaded. */
    public function ensure(string $family, int $weight, bool $italic): bool
    {
        if (! BannerFonts::isGoogle($family)) {
            return true;
        }

        $weight = BannerFonts::nearestWeight($family, $weight);
        $italic = $italic && BannerFonts::hasItalic($family);
        $face = $this->faceKey($weight, $italic);
        $manifest = $this->manifest($family);

        if (isset($manifest[$face]) && $this->filesExist($family, $manifest[$face])) {
            return true;
        }

        try {
            $name = BannerFonts::FAMILIES[$family][3];
            $css = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(15)
                ->get('https://fonts.googleapis.com/css2', ['family' => $name.':ital,wght@'.($italic ? 1 : 0).','.$weight, 'display' => 'swap'])
                ->throw()->body();

            preg_match_all('#/\*\s*([a-z-]+)\s*\*/\s*@font-face\s*\{([^}]*)\}#', $css, $blocks, PREG_SET_ORDER);
            $files = [];

            foreach ($blocks as [, $subset, $body]) {
                if (! in_array($subset, self::SUBSETS, true)
                    || ! preg_match('#src:\s*url\((https://fonts\.gstatic\.com/[^)\s]+\.woff2)\)#', $body, $src)) {
                    continue;
                }
                preg_match('#unicode-range:\s*([^;]+);#', $body, $range);

                $file = $weight.'-'.($italic ? 'italic' : 'normal').'-'.$subset.'.woff2';
                $data = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->get($src[1])->throw()->body();
                Storage::disk('public')->put(self::DIR."/$family/$file", $data);
                $files[$subset] = ['file' => $file, 'range' => trim($range[1] ?? 'U+0000-00FF')];
            }

            if (! isset($files['latin'])) {
                return false;
            }

            $manifest[$face] = ['weight' => $weight, 'italic' => $italic, 'files' => $files];
            ksort($manifest);
            Storage::disk('public')->put(self::DIR."/$family/faces.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Storage::disk('public')->put(self::DIR."/$family/font.css", $this->css($family, $manifest));

            return true;
        } catch (Throwable $e) {
            Log::warning("Banner font download failed for $family $weight".($italic ? ' italic' : '').': '.$e->getMessage());

            return false;
        }
    }

    /** Install every Google face the config uses. Returns the labels of faces that failed. */
    public function ensureFor(array $config): array
    {
        $failed = [];
        foreach ($this->facesUsed($config) as [$family, $weight, $italic]) {
            if (! $this->ensure($family, $weight, $italic)) {
                $failed[] = BannerFonts::FAMILIES[$family][0]." $weight".($italic ? ' italic' : '');
            }
        }

        return $failed;
    }

    /**
     * Stylesheets to link and font files to preload for a config.
     *
     * @return array{css: list<string>, preload: list<string>}
     */
    public function assets(array $config): array
    {
        $css = [];
        $preload = [];

        foreach ($this->facesUsed($config) as [$family, $weight, $italic]) {
            $face = $this->manifest($family)[$this->faceKey(BannerFonts::nearestWeight($family, $weight), $italic && BannerFonts::hasItalic($family))] ?? null;
            if (! $face) {
                continue; // not installed: the element falls back to the next font in its stack
            }

            $path = self::DIR."/$family/font.css";
            $css[$path] = Storage::disk('public')->url($path).'?v='.substr(md5((string) Storage::disk('public')->lastModified($path)), 0, 8);
            $preload[] = Storage::disk('public')->url(self::DIR."/$family/".$face['files']['latin']['file']);
        }

        return ['css' => array_values($css), 'preload' => array_values(array_unique($preload))];
    }

    /** URL of a family's stylesheet (for the editor), or null when nothing is installed yet. */
    public function stylesheetUrl(string $family): ?string
    {
        $path = self::DIR."/$family/font.css";

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path).'?v='.substr(md5((string) Storage::disk('public')->lastModified($path)), 0, 8)
            : null;
    }

    /** @return list<array{0: string, 1: int, 2: bool}> unique [family, weight, italic] of visible elements in Google fonts */
    public function facesUsed(array $config): array
    {
        $faces = [];
        foreach ($config['slides'] ?? [] as $slide) {
            foreach ($slide['elements'] as $el) {
                $t = $el['typography'];
                if ($el['visible'] && BannerFonts::isGoogle($t['fontFamily'])) {
                    $faces[$t['fontFamily'].'|'.$t['fontWeight'].'|'.(int) $t['italic']] = [$t['fontFamily'], $t['fontWeight'], $t['italic']];
                }
            }
        }

        return array_values($faces);
    }

    private function css(string $family, array $manifest): string
    {
        $name = BannerFonts::FAMILIES[$family][3];
        $rules = ["/* $name, self-hosted from Google Fonts (SIL Open Font License). Generated by FontInstaller. */"];

        foreach ($manifest as $face) {
            foreach ($face['files'] as $subset => $file) {
                $rules[] = "/* $subset */\n@font-face {\n  font-family: '$name';\n  font-style: ".($face['italic'] ? 'italic' : 'normal').";\n"
                    ."  font-weight: {$face['weight']};\n  font-display: swap;\n  src: url('{$file['file']}') format('woff2');\n  unicode-range: {$file['range']};\n}";
            }
        }

        return implode("\n", $rules)."\n";
    }

    private function manifest(string $family): array
    {
        $path = self::DIR."/$family/faces.json";

        return Storage::disk('public')->exists($path) ? (json_decode(Storage::disk('public')->get($path), true) ?: []) : [];
    }

    private function filesExist(string $family, array $face): bool
    {
        foreach ($face['files'] as $file) {
            if (! Storage::disk('public')->exists(self::DIR."/$family/{$file['file']}")) {
                return false;
            }
        }

        return true;
    }

    private function faceKey(int $weight, bool $italic): string
    {
        return $weight.($italic ? 'i' : 'n');
    }
}
