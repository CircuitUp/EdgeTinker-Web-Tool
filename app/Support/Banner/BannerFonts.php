<?php

namespace App\Support\Banner;

/**
 * Fonts offered in the banner editor.
 *
 * "site" fonts are already bundled with the site. "google" fonts come from a
 * curated Google Fonts list; they are downloaded to this server the first
 * time an editor picks them (FontInstaller), so visitors only ever load font
 * files from this site (no third-party requests, the CSP stays strict).
 *
 * The list is mirrored in the editor through BannerFonts::catalogue().
 */
final class BannerFonts
{
    public const DEFAULT = 'inter';

    /** key => [label, CSS stack, source, Google family name, weights, italic available] */
    public const FAMILIES = [
        'inter' => ['Inter (site font)', 'var(--et-font-sans)', 'site', null, [100, 200, 300, 400, 500, 600, 700, 800, 900], true],
        'jetbrains-mono' => ['JetBrains Mono (site font)', 'var(--et-font-mono)', 'site', null, [100, 200, 300, 400, 500, 600, 700, 800], true],
        'system' => ['System sans-serif', "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif", 'site', null, [400, 700], true],
        'serif' => ['System serif', "Georgia, 'Times New Roman', serif", 'site', null, [400, 700], true],

        'roboto' => ['Roboto', "'Roboto', Arial, sans-serif", 'google', 'Roboto', [100, 300, 400, 500, 700, 900], true],
        'open-sans' => ['Open Sans', "'Open Sans', Arial, sans-serif", 'google', 'Open Sans', [300, 400, 500, 600, 700, 800], true],
        'montserrat' => ['Montserrat', "'Montserrat', Arial, sans-serif", 'google', 'Montserrat', [100, 200, 300, 400, 500, 600, 700, 800, 900], true],
        'poppins' => ['Poppins', "'Poppins', Arial, sans-serif", 'google', 'Poppins', [100, 200, 300, 400, 500, 600, 700, 800, 900], true],
        'lato' => ['Lato', "'Lato', Arial, sans-serif", 'google', 'Lato', [100, 300, 400, 700, 900], true],
        'raleway' => ['Raleway', "'Raleway', Arial, sans-serif", 'google', 'Raleway', [100, 200, 300, 400, 500, 600, 700, 800, 900], true],
        'work-sans' => ['Work Sans', "'Work Sans', Arial, sans-serif", 'google', 'Work Sans', [100, 200, 300, 400, 500, 600, 700, 800, 900], true],
        'dm-sans' => ['DM Sans', "'DM Sans', Arial, sans-serif", 'google', 'DM Sans', [100, 200, 300, 400, 500, 600, 700, 800, 900], true],
        'rubik' => ['Rubik', "'Rubik', Arial, sans-serif", 'google', 'Rubik', [300, 400, 500, 600, 700, 800, 900], true],
        'nunito' => ['Nunito', "'Nunito', Arial, sans-serif", 'google', 'Nunito', [200, 300, 400, 500, 600, 700, 800, 900], true],
        'barlow' => ['Barlow', "'Barlow', Arial, sans-serif", 'google', 'Barlow', [100, 200, 300, 400, 500, 600, 700, 800, 900], true],
        'space-grotesk' => ['Space Grotesk', "'Space Grotesk', Arial, sans-serif", 'google', 'Space Grotesk', [300, 400, 500, 600, 700], false],
        'exo-2' => ['Exo 2', "'Exo 2', Arial, sans-serif", 'google', 'Exo 2', [100, 200, 300, 400, 500, 600, 700, 800, 900], true],
        'orbitron' => ['Orbitron', "'Orbitron', Arial, sans-serif", 'google', 'Orbitron', [400, 500, 600, 700, 800, 900], false],
        'oswald' => ['Oswald', "'Oswald', 'Arial Narrow', sans-serif", 'google', 'Oswald', [200, 300, 400, 500, 600, 700], false],
        'bebas-neue' => ['Bebas Neue', "'Bebas Neue', 'Arial Narrow', sans-serif", 'google', 'Bebas Neue', [400], false],
        'playfair-display' => ['Playfair Display', "'Playfair Display', Georgia, serif", 'google', 'Playfair Display', [400, 500, 600, 700, 800, 900], true],
        'merriweather' => ['Merriweather', "'Merriweather', Georgia, serif", 'google', 'Merriweather', [300, 400, 700, 900], true],
        'lora' => ['Lora', "'Lora', Georgia, serif", 'google', 'Lora', [400, 500, 600, 700], true],
        'source-serif-4' => ['Source Serif 4', "'Source Serif 4', Georgia, serif", 'google', 'Source Serif 4', [200, 300, 400, 500, 600, 700, 800, 900], true],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::FAMILIES[$key]);
    }

    public static function stack(string $key): string
    {
        return (self::FAMILIES[$key] ?? self::FAMILIES[self::DEFAULT])[1];
    }

    public static function isGoogle(string $key): bool
    {
        return (self::FAMILIES[$key][2] ?? null) === 'google';
    }

    /** The available weight closest to the one asked for. */
    public static function nearestWeight(string $key, int $weight): int
    {
        $weights = (self::FAMILIES[$key] ?? self::FAMILIES[self::DEFAULT])[4];
        usort($weights, fn ($a, $b) => abs($a - $weight) <=> abs($b - $weight) ?: $b <=> $a);

        return $weights[0];
    }

    public static function hasItalic(string $key): bool
    {
        return (bool) (self::FAMILIES[$key][5] ?? false);
    }

    /** For the editor: [{key, label, stack, google, weights, italic}]. */
    public static function catalogue(): array
    {
        return collect(self::FAMILIES)->map(fn (array $f, string $key) => [
            'key' => $key, 'label' => $f[0], 'stack' => $f[1], 'google' => $f[2] === 'google', 'weights' => $f[4], 'italic' => $f[5],
        ])->values()->all();
    }
}
