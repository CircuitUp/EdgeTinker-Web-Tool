<?php

namespace App\Support;

/**
 * Generates clearly marked placeholder artwork (SVG) for demo data.
 * Every image carries a "PLACEHOLDER IMAGE" label so it is never mistaken
 * for real product photography. Replace them through Content Studio.
 */
class PlaceholderArt
{
    private const LABEL = 'PLACEHOLDER IMAGE';

    /** Square product shot: a stylised circuit board with the SKU printed on it. */
    public static function product(string $name, string $sku, int $variant = 0): string
    {
        $name = self::esc($name);
        $sku = self::esc($sku);
        $label = self::LABEL;

        // Vary the board tint and chip layout a little so cards are distinguishable.
        $board = ['#5B21B6', '#4C1D95', '#6D28D9', '#451A8E'][$variant % 4];
        $chipX = [300, 340, 280, 360][$variant % 4];
        $pads = '';
        for ($i = 0; $i < 12; $i++) {
            $x = 190 + $i * 36;
            $pads .= "<rect x=\"$x\" y=\"222\" width=\"18\" height=\"26\" rx=\"3\" fill=\"#F59E0B\"/>";
            $pads .= "<rect x=\"$x\" y=\"552\" width=\"18\" height=\"26\" rx=\"3\" fill=\"#F59E0B\"/>";
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800" role="img" aria-label="Placeholder image for $name">
  <rect width="800" height="800" fill="#F5F3FF"/>
  <rect x="150" y="200" width="500" height="400" rx="24" fill="$board"/>
  <circle cx="184" cy="234" r="12" fill="#F5F3FF"/><circle cx="616" cy="234" r="12" fill="#F5F3FF"/>
  <circle cx="184" cy="566" r="12" fill="#F5F3FF"/><circle cx="616" cy="566" r="12" fill="#F5F3FF"/>
  $pads
  <rect x="$chipX" y="320" width="150" height="150" rx="8" fill="#0F172A"/>
  <path d="M220 400 H$chipX M220 440 H$chipX M560 360 H500 M560 430 H500" stroke="#C4B5FD" stroke-width="6" fill="none" stroke-linecap="round"/>
  <rect x="215" y="300" width="50" height="34" rx="4" fill="#C4B5FD"/>
  <rect x="535" y="480" width="60" height="40" rx="4" fill="#E2E8F0"/>
  <text x="400" y="525" text-anchor="middle" font-family="Consolas, Menlo, monospace" font-size="26" fill="#EDE9FE">$sku</text>
  <text x="400" y="690" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="700" fill="#0F172A">$name</text>
  <rect x="270" y="80" width="260" height="44" rx="22" fill="#FFFFFF" stroke="#6D28D9" stroke-width="2"/>
  <text x="400" y="110" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="20" font-weight="700" letter-spacing="2" fill="#5B21B6">$label</text>
</svg>
SVG;
    }

    /** 4:3 category image for tiles and the mega menu panel. */
    public static function category(string $name, int $variant = 0): string
    {
        $name = self::esc($name);
        $label = self::LABEL;
        $tint = ['#EDE9FE', '#DDD6FE', '#F5F3FF', '#EDE9FE', '#DDD6FE'][$variant % 5];
        $traces = self::traces(800, 600, $variant + 3, '#C4B5FD', 4);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" width="800" height="600" role="img" aria-label="Placeholder image for $name">
  <rect width="800" height="600" fill="$tint"/>
  $traces
  <rect x="250" y="170" width="300" height="220" rx="20" fill="#5B21B6"/>
  <rect x="330" y="225" width="140" height="110" rx="8" fill="#0F172A"/>
  <circle cx="278" cy="198" r="9" fill="$tint"/><circle cx="522" cy="198" r="9" fill="$tint"/>
  <circle cx="278" cy="362" r="9" fill="$tint"/><circle cx="522" cy="362" r="9" fill="$tint"/>
  <text x="400" y="470" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="34" font-weight="700" fill="#2E1065">$name</text>
  <rect x="270" y="505" width="260" height="40" rx="20" fill="#FFFFFF" stroke="#6D28D9" stroke-width="2"/>
  <text x="400" y="532" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="700" letter-spacing="2" fill="#5B21B6">$label</text>
</svg>
SVG;
    }

    /** Wide hero slide background. Text is overlaid by the page, so this stays abstract. */
    public static function slide(int $variant = 0): string
    {
        $label = self::LABEL;
        $traces = self::traces(1920, 900, $variant + 11, '#7C3AED', 5);
        $boardX = [1180, 1240, 1120][$variant % 3];

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1920 900" width="1920" height="900" role="img" aria-label="Placeholder hero image">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#2E1065"/><stop offset="1" stop-color="#5B21B6"/>
    </linearGradient>
  </defs>
  <rect width="1920" height="900" fill="url(#g)"/>
  $traces
  <rect x="$boardX" y="230" width="520" height="400" rx="28" fill="#6D28D9" stroke="#A78BFA" stroke-width="4"/>
  <g transform="translate($boardX 230)">
    <rect x="170" y="120" width="180" height="160" rx="10" fill="#0F172A"/>
    <rect x="50" y="60" width="70" height="44" rx="6" fill="#C4B5FD"/>
    <rect x="400" y="300" width="70" height="50" rx="6" fill="#E2E8F0"/>
    <circle cx="36" cy="36" r="13" fill="#2E1065"/><circle cx="484" cy="36" r="13" fill="#2E1065"/>
    <circle cx="36" cy="364" r="13" fill="#2E1065"/><circle cx="484" cy="364" r="13" fill="#2E1065"/>
    <path d="M60 200 H170 M60 240 H170 M350 160 H460 M350 220 H430 V300" stroke="#F59E0B" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
  </g>
  <rect x="1230" y="150" width="300" height="46" rx="23" fill="#FFFFFF" opacity="0.92"/>
  <text x="1380" y="181" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="20" font-weight="700" letter-spacing="2" fill="#5B21B6">$label</text>
</svg>
SVG;
    }

    /** Deterministic circuit-trace decoration. */
    private static function traces(int $w, int $h, int $seed, string $colour, int $stroke): string
    {
        mt_srand($seed);
        $out = '';
        for ($i = 0; $i < 14; $i++) {
            $x = mt_rand(0, $w);
            $y = mt_rand(0, $h);
            $dx = mt_rand(80, 320) * (mt_rand(0, 1) ? 1 : -1);
            $dy = mt_rand(60, 200) * (mt_rand(0, 1) ? 1 : -1);
            $x2 = $x + $dx;
            $y2 = $y + $dy;
            $out .= "<path d=\"M$x $y H$x2 V$y2\" stroke=\"$colour\" stroke-width=\"$stroke\" fill=\"none\" opacity=\"0.45\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>";
            $out .= "<circle cx=\"$x2\" cy=\"$y2\" r=\"".($stroke + 4)."\" fill=\"$colour\" opacity=\"0.6\"/>";
        }
        mt_srand();

        return $out;
    }

    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
