<?php

namespace App\Support\Banner;

/**
 * Turns a BannerConfig into the inline CSS custom properties read by the
 * banner stylesheet (resources/css/banner.css). Responsive values are written
 * once per breakpoint (-d, -t, -m, already resolved with inheritance) and the
 * stylesheet picks the right one with container queries, so the same markup
 * renders correctly on the live site and in the editor's resizable canvas.
 *
 * resources/js/banner/styles.js is a line-by-line mirror of this class (the
 * editor needs it to re-render while dragging). Both are checked against the
 * same fixtures in tests/fixtures/banner.
 */
final class BannerStyles
{
    private const SUFFIX = ['desktop' => 'd', 'tablet' => 't', 'mobile' => 'm'];

    private const BUTTON_SHADOWS = [
        'none' => 'none',
        'sm' => '0 1px 2px rgba(15,23,42,0.2)',
        'md' => '0 4px 12px rgba(15,23,42,0.25)',
        'lg' => '0 10px 30px rgba(15,23,42,0.35)',
    ];

    /** Banner heights per breakpoint. */
    public static function banner(array $config): string
    {
        $vars = [];
        foreach (self::SUFFIX as $bp => $s) {
            $vars[] = "--bnr-h-$s:".Color::num($config['height'][$bp]).'px';
        }

        return implode(';', $vars);
    }

    /** The CSS background of an overlay: "none", a colour or a gradient. */
    public static function overlayBackground(array $overlay): string
    {
        if (! $overlay['enabled']) {
            return 'none';
        }

        if ($overlay['mode'] === 'solid') {
            return Color::css($overlay['solid']['color']);
        }

        $g = $overlay['gradient'];
        $stops = $g['stops'];
        usort($stops, fn ($a, $b) => $a['position'] <=> $b['position']);
        $list = implode(',', array_map(fn ($stop) => Color::css($stop['color']).' '.Color::num($stop['position']).'%', $stops));

        return $overlay['mode'] === 'radial-gradient'
            ? 'radial-gradient('.$g['shape'].' at '.Color::num($g['center']['x']).'% '.Color::num($g['center']['y']).'%,'.$list.')'
            : 'linear-gradient('.Color::num($g['angle']).'deg,'.$list.')';
    }

    /** Overlay background, opacity and blend mode per breakpoint. */
    public static function overlay(array $slide): string
    {
        $vars = [];
        foreach (self::SUFFIX as $bp => $s) {
            $overlay = BannerConfig::resolve($slide['overlay'], $bp);
            $vars[] = "--ov-$s:".self::overlayBackground($overlay);
            $vars[] = "--ovo-$s:".Color::num($overlay['enabled'] ? $overlay['opacity'] : 0);
            $vars[] = "--ovb-$s:".$overlay['blendMode'];
        }

        return implode(';', $vars);
    }

    /** Background colour and image focal point / fit of a slide. */
    public static function background(array $slide): string
    {
        $bg = $slide['background'];

        return 'background-color:'.Color::css($bg['color'])
            .';--bg-pos:'.Color::num($bg['position']['x']).'% '.Color::num($bg['position']['y']).'%'
            .';--bg-fit:'.$bg['size'];
    }

    /** Everything about one element: layout per breakpoint, then typography, then button colours. */
    public static function element(array $el, int $layer): string
    {
        $t = $el['typography'];
        $vars = [];

        foreach (self::SUFFIX as $bp => $s) {
            $p = BannerConfig::resolve($el['position'], $bp);
            $w = BannerConfig::resolve($el['width'], $bp);
            $rotate = 'rotate('.Color::num($el['rotation']).'deg)';

            $vars[] = "--pos-$s:".($p ? 'absolute' : 'relative');
            $vars[] = "--l-$s:".($p ? Color::num($p['x']).'%' : 'auto');
            $vars[] = "--t-$s:".($p ? Color::num($p['y']).'%' : 'auto');
            $vars[] = "--w-$s:".($w !== null ? Color::num($w).'%' : 'auto');
            $vars[] = "--tf-$s:".($p ? 'translate('.($p['ax'] ? '-'.$p['ax'] : '0').'%,'.($p['ay'] ? '-'.$p['ay'] : '0').'%) '.$rotate : $rotate);
            $vars[] = "--mt-$s:".($p ? '0' : Color::num($el['gap'])).'px';
            $vars[] = "--fs-$s:".Color::num(BannerConfig::resolve($t['fontSize'], $bp)).'px';
            $vars[] = "--lh-$s:".Color::num(BannerConfig::resolve($t['lineHeight'], $bp));
        }

        $decoration = trim(($t['underline'] ? 'underline ' : '').($t['strike'] ? 'line-through' : ''));
        $shadow = $t['shadow'];

        $vars[] = 'z-index:'.($layer + 1);
        $vars[] = 'font-family:'.BannerFonts::stack($t['fontFamily']);
        $vars[] = 'font-weight:'.$t['fontWeight'];
        $vars[] = 'font-style:'.($t['italic'] ? 'italic' : 'normal');
        $vars[] = 'letter-spacing:'.Color::num($t['letterSpacing']).'em';
        $vars[] = 'text-align:'.$t['textAlign'];
        $vars[] = 'text-transform:'.$t['textTransform'];
        $vars[] = 'text-decoration-line:'.($decoration ?: 'none');
        $vars[] = 'text-shadow:'.($shadow ? Color::num($shadow['x']).'px '.Color::num($shadow['y']).'px '.Color::num($shadow['blur']).'px '.Color::css($shadow['color']) : 'none');

        if ($el['type'] === 'button' && $el['button']) {
            $b = $el['button'];
            $vars[] = '--fg:'.Color::css($b['text']);
            $vars[] = '--bg:'.Color::css($b['bg']);
            $vars[] = '--bd:'.Color::css($b['border']);
            $vars[] = '--fg-h:'.Color::css($b['hover']['text']);
            $vars[] = '--bg-h:'.Color::css($b['hover']['bg']);
            $vars[] = '--bd-h:'.Color::css($b['hover']['border']);
            $vars[] = 'border-width:'.$b['borderWidth'].'px';
            $vars[] = 'border-radius:'.$b['radius'].'px';
            $vars[] = 'padding:'.$b['py'].'px '.$b['px'].'px';
            $vars[] = 'box-shadow:'.self::BUTTON_SHADOWS[$b['shadow']];
        } else {
            $vars[] = 'color:'.Color::css($t['color']);
        }

        return implode(';', $vars);
    }
}
