<?php

namespace App\Support\Banner;

use App\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The home page banner: one versioned JSON document (the single source of
 * truth for the live banner and the Content Studio editor).
 *
 *   {version, frame, height: {desktop, tablet, mobile}, scrim, slides: [Slide]}
 *
 *   Slide   {id, name, background, overlay: {desktop, tablet|null, mobile|null}, elements: [Element]}
 *   Overlay {enabled, mode: solid|linear-gradient|radial-gradient, solid: {color},
 *            gradient: {angle, shape, center: {x, y}, stops: [{color, position}] (2–4)}, opacity, blendMode}
 *   Element {id, type: text|button, name, visible, locked, tag, content, href, newTab, icon,
 *            position: {desktop|tablet|mobile: {x, y, ax, ay} | null}, width: {…: % | null},
 *            rotation, gap, typography: {…}, button: {…} (buttons only)}
 *
 * Breakpoints: desktop ≥ 1024 px, tablet 640–1023 px, mobile < 640 px (the
 * width of the banner itself). A null tablet/mobile value inherits from the
 * next larger breakpoint. A null desktop position means "auto": the element
 * flows in a column with the others, vertically centred on the left of the
 * page content (the original banner layout). x/y are percentages of the
 * banner's content frame; ax/ay (0, 50, 100) say which point of the element
 * sits at x/y. Colours: see Color.
 *
 * normalise() turns any input into a complete, well-typed config (unknown
 * keys dropped, numbers clamped, text stripped of markup); rules() then
 * checks what cannot be repaired (missing text, unsafe links, missing files).
 */
final class BannerConfig
{
    public const VERSION = 1;

    public const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];

    /** Smallest banner width (px) at which each breakpoint applies. */
    public const BREAKPOINT_MIN = ['desktop' => 1024, 'tablet' => 640, 'mobile' => 0];

    public const MAX_SLIDES = 6;

    public const MAX_ELEMENTS = 12;

    public const OVERLAY_MODES = ['solid', 'linear-gradient', 'radial-gradient'];

    public const BLEND_MODES = ['normal', 'multiply', 'overlay', 'screen', 'soft-light'];

    public const TEXT_TAGS = ['h2', 'h3', 'p'];

    public const BUTTON_ICONS = ['none', 'arrow-right', 'external'];

    public const SHADOWS = ['none', 'sm', 'md', 'lg'];

    /** Internal path, https address, page anchor, e-mail or phone link. No javascript:, data: or protocol-relative links. */
    public const LINK_PATTERN = '#^(/(?!/)\S*|https://\S+|\#[A-Za-z][\w-]*|mailto:[^\s@]+@[^\s@]+|tel:\+?[\d\s()-]{3,})$#';

    // ── Defaults (the original hard-coded banner design) ─────────────────────

    public static function primary900(float $alpha = 1): array
    {
        return Color::make(46, 16, 101, $alpha);
    }

    public static function white(float $alpha = 1): array
    {
        return Color::make(255, 255, 255, $alpha);
    }

    /** A banner with no slides: the home page shows no banner until one is added. */
    public static function empty(): array
    {
        return ['version' => self::VERSION, 'frame' => 'container', 'height' => ['desktop' => 576, 'tablet' => 512, 'mobile' => 480], 'scrim' => true, 'slides' => []];
    }

    /** Overlay of the original design: purple fading out to the right on desktop, an even tint on smaller screens. */
    public static function defaultOverlay(): array
    {
        return [
            'desktop' => self::overlay('linear-gradient', gradient: ['angle' => 90, 'shape' => 'ellipse', 'center' => ['x' => 50, 'y' => 50], 'stops' => [
                ['color' => self::primary900(0.9), 'position' => 15],
                ['color' => self::primary900(0.6), 'position' => 45],
                ['color' => self::primary900(0), 'position' => 70],
            ]]),
            'tablet' => self::overlay('solid', solid: self::primary900(0.65)),
            'mobile' => null,
        ];
    }

    public static function overlay(string $mode = 'solid', ?array $solid = null, ?array $gradient = null, bool $enabled = true): array
    {
        return [
            'enabled' => $enabled,
            'mode' => $mode,
            'solid' => ['color' => $solid ?? self::primary900(0.65)],
            'gradient' => $gradient ?? ['angle' => 90, 'shape' => 'ellipse', 'center' => ['x' => 50, 'y' => 50], 'stops' => [
                ['color' => self::primary900(0.9), 'position' => 0],
                ['color' => self::primary900(0), 'position' => 100],
            ]],
            'opacity' => 1,
            'blendMode' => 'normal',
        ];
    }

    private static function textShadow(): array
    {
        return ['x' => 0, 'y' => 2, 'blur' => 16, 'color' => Color::make(15, 23, 42, 0.55)];
    }

    /** A new text element; $role picks the heading or sub-text style of the original design. */
    public static function textElement(string $content = 'New text', string $role = 'text'): array
    {
        $heading = $role === 'heading';

        return [
            'id' => self::newId('t'), 'type' => 'text', 'name' => $heading ? 'Heading' : ($role === 'sub' ? 'Sub-text' : 'Text'),
            'visible' => true, 'locked' => false, 'tag' => $heading ? 'h2' : 'p',
            'content' => $content, 'href' => '', 'newTab' => false, 'icon' => 'none',
            'position' => ['desktop' => null, 'tablet' => null, 'mobile' => null],
            'width' => ['desktop' => null, 'tablet' => null, 'mobile' => null],
            'rotation' => 0,
            'gap' => $role === 'sub' ? 16 : ($role === 'heading' ? 0 : 16),
            'typography' => [
                'fontFamily' => BannerFonts::DEFAULT,
                'fontWeight' => $heading ? 700 : 400,
                // Tailwind text-3xl/4xl/5xl (heading) and text-lg/xl (sub-text) of the original design.
                'fontSize' => $heading ? ['desktop' => 48, 'tablet' => 36, 'mobile' => 30] : ['desktop' => 20, 'tablet' => null, 'mobile' => 18],
                'lineHeight' => $heading ? ['desktop' => 1.1, 'tablet' => 1.111, 'mobile' => 1.2] : ['desktop' => 1.4, 'tablet' => null, 'mobile' => 1.556],
                'letterSpacing' => $heading ? -0.025 : 0,
                'textAlign' => 'left', 'textTransform' => 'none',
                'italic' => false, 'underline' => false, 'strike' => false,
                'color' => self::white(),
                'shadow' => self::textShadow(),
            ],
            'button' => null,
        ];
    }

    /** A new button in the style of the original design (white, purple text). */
    public static function buttonElement(string $label = 'Learn more', string $href = '/products', string $icon = 'arrow-right'): array
    {
        return [
            'id' => self::newId('b'), 'type' => 'button', 'name' => 'Button',
            'visible' => true, 'locked' => false, 'tag' => null,
            'content' => $label, 'href' => $href, 'newTab' => false, 'icon' => $icon,
            'position' => ['desktop' => null, 'tablet' => null, 'mobile' => null],
            'width' => ['desktop' => null, 'tablet' => null, 'mobile' => null],
            'rotation' => 0,
            'gap' => 32,
            'typography' => [
                'fontFamily' => BannerFonts::DEFAULT, 'fontWeight' => 600,
                'fontSize' => ['desktop' => 18, 'tablet' => null, 'mobile' => null],
                'lineHeight' => ['desktop' => 1.556, 'tablet' => null, 'mobile' => null],
                'letterSpacing' => 0, 'textAlign' => 'center', 'textTransform' => 'none',
                'italic' => false, 'underline' => false, 'strike' => false,
                'color' => self::primary900(),
                'shadow' => self::textShadow(),
            ],
            'button' => [
                'bg' => self::white(), 'text' => self::primary900(), 'border' => self::white(0),
                'borderWidth' => 0, 'radius' => 6, 'px' => 24, 'py' => 12,
                'hover' => ['bg' => Color::make(245, 243, 255), 'text' => self::primary900(), 'border' => self::white(0)],
                'shadow' => 'none',
            ],
        ];
    }

    /** A slide in the original design: heading, optional sub-text and optional button over a photo. */
    public static function slide(string $heading = 'Your headline here', ?string $subText = null, ?string $buttonLabel = null, ?string $buttonUrl = null,
        array $image = ['media_id' => null, 'path' => null], string $alt = '', ?string $name = null): array
    {
        $elements = [self::textElement($heading, 'heading')];
        if (filled($subText)) {
            $elements[] = self::textElement($subText, 'sub');
        }
        if (filled($buttonLabel) && filled($buttonUrl)) {
            $elements[] = self::buttonElement($buttonLabel, $buttonUrl);
        }

        return [
            'id' => self::newId('s'),
            'name' => $name ?? Str::limit($heading, 40, '…', preserveWords: true),
            'background' => [
                'type' => 'image', 'image' => $image, 'video' => ['media_id' => null],
                'color' => self::primary900(), 'alt' => $alt, 'position' => ['x' => 50, 'y' => 50], 'size' => 'cover',
            ],
            'overlay' => self::defaultOverlay(),
            'elements' => $elements,
        ];
    }

    /**
     * The banner built from the rows of the old banner_slides table, looking
     * exactly as it did. $mediaIdForPath maps an image path to its media id.
     */
    public static function fromLegacySlides(iterable $rows, ?callable $mediaIdForPath = null): array
    {
        $config = self::empty();

        foreach ($rows as $row) {
            $row = (array) $row;
            $path = $row['image_path'] ?? null;
            $mediaId = $path && $mediaIdForPath ? $mediaIdForPath($path) : null;

            $config['slides'][] = self::slide((string) ($row['heading'] ?? ''), $row['sub_text'] ?? null, $row['button_label'] ?? null, $row['button_url'] ?? null,
                ['media_id' => $mediaId, 'path' => $mediaId ? null : $path], (string) ($row['image_alt'] ?? ''));
        }

        return self::normalise($config);
    }

    /**
     * Keep each slide's background and words but restore the original design:
     * overlay, element styles and layout. Extra elements are dropped.
     */
    public static function resetDesign(array $config): array
    {
        $config = self::normalise($config);
        $fresh = self::empty();
        $fresh['slides'] = array_map(function (array $slide) {
            $texts = collect($slide['elements'])->where('type', 'text')->values();
            $heading = $texts->firstWhere('tag', '!=', 'p') ?? $texts->first();
            $sub = $texts->first(fn ($el) => $el['id'] !== ($heading['id'] ?? null));
            $button = collect($slide['elements'])->firstWhere('type', 'button');

            $new = self::slide($heading['content'] ?? 'Your headline here', $sub['content'] ?? null, $button['content'] ?? null, $button['href'] ?? null, name: $slide['name']);
            $new['id'] = $slide['id'];
            $new['background'] = $slide['background'];

            return $new;
        }, $config['slides']);

        return self::normalise($fresh);
    }

    // ── Reading stored data ──────────────────────────────────────────────────

    /** A stored config, repaired where possible; the empty banner when unusable. */
    public static function fromStored(mixed $stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (! is_array($stored) || (int) ($stored['version'] ?? 0) !== self::VERSION) {
            return self::empty();
        }

        try {
            return self::normalise($stored);
        } catch (Throwable) {
            return self::empty();
        }
    }

    // ── Normalising ──────────────────────────────────────────────────────────

    public static function normalise(mixed $input): array
    {
        $in = is_array($input) ? $input : [];
        $height = is_array($in['height'] ?? null) ? $in['height'] : [];
        $defaults = self::empty()['height'];

        $slides = [];
        $ids = [];
        foreach (array_slice(array_values(array_filter((array) ($in['slides'] ?? []), 'is_array')), 0, self::MAX_SLIDES) as $i => $slide) {
            $slides[] = self::normaliseSlide($slide, $i, $ids);
        }

        return [
            'version' => self::VERSION,
            'frame' => self::pick($in['frame'] ?? null, ['container', 'full'], 'container'),
            'height' => [
                'desktop' => self::int($height['desktop'] ?? null, 240, 1200, $defaults['desktop']),
                'tablet' => self::int($height['tablet'] ?? null, 200, 1200, $defaults['tablet']),
                'mobile' => self::int($height['mobile'] ?? null, 200, 1200, $defaults['mobile']),
            ],
            'scrim' => self::bool($in['scrim'] ?? true),
            'slides' => $slides,
        ];
    }

    private static function normaliseSlide(array $in, int $index, array &$ids): array
    {
        $bg = is_array($in['background'] ?? null) ? $in['background'] : [];
        $overlay = is_array($in['overlay'] ?? null) ? $in['overlay'] : [];
        $defaultOverlay = self::defaultOverlay();

        $elements = [];
        foreach (array_slice(array_values(array_filter((array) ($in['elements'] ?? []), 'is_array')), 0, self::MAX_ELEMENTS) as $el) {
            $elements[] = self::normaliseElement($el, $ids);
        }

        return [
            'id' => self::uniqueId($in['id'] ?? null, 's', $ids),
            'name' => self::text($in['name'] ?? '', 60) ?: 'Slide '.($index + 1),
            'background' => [
                'type' => self::pick($bg['type'] ?? null, ['image', 'video', 'color'], 'image'),
                'image' => self::mediaRef($bg['image'] ?? null),
                'video' => ['media_id' => self::nullableInt(data_get($bg, 'video.media_id'))],
                'color' => Color::parse($bg['color'] ?? null) ?? self::primary900(),
                'alt' => self::text($bg['alt'] ?? '', 255),
                'position' => self::point($bg['position'] ?? null, 0, 100, 50),
                'size' => self::pick($bg['size'] ?? null, ['cover', 'contain'], 'cover'),
            ],
            'overlay' => [
                'desktop' => self::normaliseOverlay(array_key_exists('desktop', $overlay) ? $overlay['desktop'] : $defaultOverlay['desktop']),
                'tablet' => isset($overlay['tablet']) ? self::normaliseOverlay($overlay['tablet']) : (array_key_exists('tablet', $overlay) ? null : self::normaliseOverlay($defaultOverlay['tablet'])),
                'mobile' => isset($overlay['mobile']) ? self::normaliseOverlay($overlay['mobile']) : null,
            ],
            'elements' => $elements,
        ];
    }

    private static function normaliseOverlay(mixed $input): array
    {
        $in = is_array($input) ? $input : [];
        $gradient = is_array($in['gradient'] ?? null) ? $in['gradient'] : [];
        $fallback = self::overlay()['gradient'];

        $stops = [];
        foreach (array_slice(array_values(array_filter((array) ($gradient['stops'] ?? []), 'is_array')), 0, 4) as $stop) {
            $stops[] = ['color' => Color::parse($stop['color'] ?? null) ?? self::primary900(0), 'position' => self::num($stop['position'] ?? 0, 0, 100, 0)];
        }
        // A gradient always has at least two stops.
        while (count($stops) < 2) {
            $stops[] = $fallback['stops'][count($stops)];
        }

        return [
            'enabled' => self::bool($in['enabled'] ?? true),
            'mode' => self::pick($in['mode'] ?? null, self::OVERLAY_MODES, 'solid'),
            'solid' => ['color' => Color::parse(data_get($in, 'solid.color')) ?? self::primary900(0.65)],
            'gradient' => [
                'angle' => self::num($gradient['angle'] ?? 90, 0, 360, 90),
                'shape' => self::pick($gradient['shape'] ?? null, ['circle', 'ellipse'], 'ellipse'),
                'center' => self::point($gradient['center'] ?? null, 0, 100, 50),
                'stops' => $stops,
            ],
            'opacity' => self::num($in['opacity'] ?? 1, 0, 1, 1),
            'blendMode' => self::pick($in['blendMode'] ?? null, self::BLEND_MODES, 'normal'),
        ];
    }

    private static function normaliseElement(array $in, array &$ids): array
    {
        $type = self::pick($in['type'] ?? null, ['text', 'button'], 'text');
        $template = $type === 'button' ? self::buttonElement() : self::textElement();
        $typo = is_array($in['typography'] ?? null) ? $in['typography'] : [];
        $t = $template['typography'];
        $family = is_string($typo['fontFamily'] ?? null) && BannerFonts::exists($typo['fontFamily']) ? $typo['fontFamily'] : BannerFonts::DEFAULT;
        $shadow = $typo['shadow'] ?? null;

        $element = [
            'id' => self::uniqueId($in['id'] ?? null, $type === 'button' ? 'b' : 't', $ids),
            'type' => $type,
            'name' => self::text($in['name'] ?? '', 60) ?: $template['name'],
            'visible' => self::bool($in['visible'] ?? true),
            'locked' => self::bool($in['locked'] ?? false),
            'tag' => $type === 'text' ? self::pick($in['tag'] ?? null, self::TEXT_TAGS, 'p') : null,
            'content' => self::plain($in['content'] ?? '', $type === 'button' ? 60 : 300),
            'href' => $type === 'button' ? self::text($in['href'] ?? '', 500) : '',
            'newTab' => $type === 'button' && self::bool($in['newTab'] ?? false),
            'icon' => $type === 'button' ? self::pick($in['icon'] ?? null, self::BUTTON_ICONS, 'none') : 'none',
            'position' => self::responsive($in['position'] ?? null, fn ($p) => is_array($p) ? [
                'x' => self::num($p['x'] ?? 0, -50, 150, 0),
                'y' => self::num($p['y'] ?? 0, -50, 150, 0),
                'ax' => self::pick((int) ($p['ax'] ?? 0), [0, 50, 100], 0),
                'ay' => self::pick((int) ($p['ay'] ?? 0), [0, 50, 100], 0),
            ] : null, desktopRequired: false),
            'width' => self::responsive($in['width'] ?? null, fn ($w) => is_numeric($w) ? self::num($w, 5, 100, 100) : null, desktopRequired: false),
            'rotation' => self::num($in['rotation'] ?? 0, -180, 180, 0),
            'gap' => self::int($in['gap'] ?? $template['gap'], 0, 200, $template['gap']),
            'typography' => [
                'fontFamily' => $family,
                'fontWeight' => is_numeric($typo['fontWeight'] ?? null) ? max(100, min(900, (int) round($typo['fontWeight'] / 100) * 100)) : $t['fontWeight'],
                'fontSize' => self::responsive($typo['fontSize'] ?? null, fn ($v) => is_numeric($v) ? self::num($v, 8, 200, 16) : null, $t['fontSize']['desktop']),
                'lineHeight' => self::responsive($typo['lineHeight'] ?? null, fn ($v) => is_numeric($v) ? self::num($v, 0.7, 3, 1.4) : null, $t['lineHeight']['desktop']),
                'letterSpacing' => self::num($typo['letterSpacing'] ?? $t['letterSpacing'], -0.2, 0.5, 0),
                'textAlign' => self::pick($typo['textAlign'] ?? null, ['left', 'center', 'right'], $t['textAlign']),
                'textTransform' => self::pick($typo['textTransform'] ?? null, ['none', 'uppercase', 'lowercase', 'capitalize'], 'none'),
                'italic' => self::bool($typo['italic'] ?? false),
                'underline' => self::bool($typo['underline'] ?? false),
                'strike' => self::bool($typo['strike'] ?? false),
                'color' => Color::parse($typo['color'] ?? null) ?? $t['color'],
                'shadow' => is_array($shadow) ? [
                    'x' => self::num($shadow['x'] ?? 0, -50, 50, 0),
                    'y' => self::num($shadow['y'] ?? 0, -50, 50, 0),
                    'blur' => self::num($shadow['blur'] ?? 0, 0, 100, 0),
                    'color' => Color::parse($shadow['color'] ?? null) ?? Color::make(0, 0, 0, 0.5),
                ] : null,
            ],
            'button' => null,
        ];

        if ($type === 'button') {
            $b = is_array($in['button'] ?? null) ? $in['button'] : [];
            $d = $template['button'];
            $hover = is_array($b['hover'] ?? null) ? $b['hover'] : [];
            $element['button'] = [
                'bg' => Color::parse($b['bg'] ?? null) ?? $d['bg'],
                'text' => Color::parse($b['text'] ?? null) ?? $d['text'],
                'border' => Color::parse($b['border'] ?? null) ?? $d['border'],
                'borderWidth' => self::int($b['borderWidth'] ?? 0, 0, 12, 0),
                'radius' => self::int($b['radius'] ?? $d['radius'], 0, 100, $d['radius']),
                'px' => self::int($b['px'] ?? $d['px'], 0, 120, $d['px']),
                'py' => self::int($b['py'] ?? $d['py'], 0, 80, $d['py']),
                'hover' => [
                    'bg' => Color::parse($hover['bg'] ?? null) ?? $d['hover']['bg'],
                    'text' => Color::parse($hover['text'] ?? null) ?? $d['hover']['text'],
                    'border' => Color::parse($hover['border'] ?? null) ?? $d['hover']['border'],
                ],
                'shadow' => self::pick($b['shadow'] ?? null, self::SHADOWS, 'none'),
            ];
            // The button's text colour lives in button.text; keep typography.color in step for the contrast check.
            $element['typography']['color'] = $element['button']['text'];
        }

        return $element;
    }

    // ── Validation ───────────────────────────────────────────────────────────

    /** Laravel validation rules for a normalised config. */
    public static function rules(): array
    {
        return [
            'slides' => ['array', 'min:1', 'max:'.self::MAX_SLIDES],
            'slides.*.background.image.media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', Media::KIND_IMAGE)],
            'slides.*.background.image.path' => ['nullable', 'string', 'starts_with:placeholders/,media/', 'not_regex:/\.\./'],
            'slides.*.background.video.media_id' => ['nullable', 'required_if:slides.*.background.type,video', 'integer', Rule::exists('media', 'id')->where('kind', Media::KIND_VIDEO)],
            'slides.*.elements' => ['array', 'max:'.self::MAX_ELEMENTS],
            'slides.*.elements.*.content' => ['required', 'string', 'max:300'],
            'slides.*.elements.*.href' => ['nullable', 'required_if:slides.*.elements.*.type,button', 'string', 'max:500', 'regex:'.self::LINK_PATTERN],
        ];
    }

    public static function messages(): array
    {
        return [
            'slides.min' => 'The banner needs at least one slide.',
            'slides.max' => 'The banner can have at most '.self::MAX_SLIDES.' slides.',
            'slides.*.elements.max' => 'A slide can have at most '.self::MAX_ELEMENTS.' text and button elements.',
            'slides.*.elements.*.content.required' => 'A text or button element is empty. Type some text or delete it.',
            'slides.*.elements.*.href.required_if' => 'A button needs a link.',
            'slides.*.elements.*.href.regex' => 'A button link must be a page on this site (starting with /), a full https:// address, a #section, mailto: or tel: link.',
            'slides.*.background.image.media_id.exists' => 'A slide background image is no longer in the media library. Choose another one.',
            'slides.*.background.video.media_id.required_if' => 'Choose a video for a slide whose background is set to video.',
            'slides.*.background.video.media_id.exists' => 'A slide background video is no longer in the media library. Choose another one.',
            'slides.*.background.image.path.starts_with' => 'A slide background image could not be found.',
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Breakpoint values; tablet and mobile may be null (inherit). Desktop falls back to $desktopDefault. */
    private static function responsive(mixed $input, callable $clean, mixed $desktopDefault = null, bool $desktopRequired = true): array
    {
        $in = is_array($input) ? $input : [];
        $desktop = $clean($in['desktop'] ?? null);

        return [
            'desktop' => $desktop ?? ($desktopRequired ? $desktopDefault : null),
            'tablet' => $clean($in['tablet'] ?? null),
            'mobile' => $clean($in['mobile'] ?? null),
        ];
    }

    /** Resolve a breakpoint value with inheritance (mobile ← tablet ← desktop). */
    public static function resolve(array $values, string $breakpoint): mixed
    {
        return match ($breakpoint) {
            'desktop' => $values['desktop'],
            'tablet' => $values['tablet'] ?? $values['desktop'],
            default => $values['mobile'] ?? $values['tablet'] ?? $values['desktop'],
        };
    }

    public static function newId(string $prefix): string
    {
        return $prefix.Str::lower(Str::random(8));
    }

    private static function uniqueId(mixed $id, string $prefix, array &$ids): string
    {
        $id = is_string($id) && preg_match('/^[a-z][a-z0-9_-]{2,31}$/', $id) && ! isset($ids[$id]) ? $id : self::newId($prefix);
        $ids[$id] = true;

        return $id;
    }

    private static function mediaRef(mixed $in): array
    {
        $in = is_array($in) ? $in : [];
        $mediaId = self::nullableInt($in['media_id'] ?? null);

        return ['media_id' => $mediaId, 'path' => $mediaId ? null : (self::text($in['path'] ?? '', 500) ?: null)];
    }

    private static function point(mixed $in, float $min, float $max, float $default): array
    {
        $in = is_array($in) ? $in : [];

        return ['x' => self::num($in['x'] ?? $default, $min, $max, $default), 'y' => self::num($in['y'] ?? $default, $min, $max, $default)];
    }

    private static function pick(mixed $value, array $allowed, mixed $default): mixed
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private static function num(mixed $value, float $min, float $max, float $default): float|int
    {
        if (! is_numeric($value)) {
            return $default;
        }
        $n = round(max($min, min($max, (float) $value)), 3);

        return floor($n) == $n ? (int) $n : $n;
    }

    private static function int(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? max($min, min($max, (int) round((float) $value))) : $default;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** One line of plain text. */
    private static function text(mixed $value, int $max): string
    {
        return is_scalar($value) ? mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value))), 0, $max) : '';
    }

    /** Plain text that may contain line breaks (shown with white-space: pre-line). */
    private static function plain(mixed $value, int $max): string
    {
        if (! is_scalar($value)) {
            return '';
        }
        $text = strip_tags(str_replace(["\r\n", "\r"], "\n", (string) $value));
        $text = preg_replace('/[^\P{C}\n]/u', '', $text);            // control characters except line breaks
        $text = preg_replace(["/[ \t]+/u", "/ *\n */u", "/\n{3,}/"], [' ', "\n", "\n\n"], $text);

        return mb_substr(trim($text), 0, $max);
    }
}
