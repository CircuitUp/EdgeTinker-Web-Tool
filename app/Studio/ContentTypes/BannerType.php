<?php

namespace App\Studio\ContentTypes;

use App\Models\Banner;
use App\Models\Media;
use App\Support\Banner\BannerConfig;
use App\Support\Banner\BannerFonts;
use App\Support\Banner\Color;
use App\Support\Banner\FontInstaller;
use App\Support\HomePage;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Validation\ValidationException;

/**
 * The home page banner. One fixed item (it cannot be created or deleted);
 * the payload is a complete BannerConfig, edited in the visual banner editor.
 */
class BannerType extends ContentType
{
    public function key(): string
    {
        return 'banner';
    }

    public function label(): string
    {
        return 'Home banner';
    }

    /** The id of the home banner row, creating it on first use. */
    public static function homeId(): int
    {
        return Banner::home()->id;
    }

    public function normalise(array $payload, ?int $entityId): array
    {
        return BannerConfig::normalise($payload);
    }

    public function rules(array $payload, ?int $entityId): array
    {
        return BannerConfig::rules();
    }

    public function validationMessages(): array
    {
        return BannerConfig::messages();
    }

    public function title(array $payload): string
    {
        return 'Home banner';
    }

    public function current(int $entityId): ?array
    {
        return Banner::find($entityId)?->design();
    }

    public function apply(array $payload, ?int $entityId): int
    {
        $banner = Banner::home();
        $banner->update(['config' => $payload]);

        // Fonts are normally downloaded when the editor picks them; make sure they are here before visitors need them.
        app(FontInstaller::class)->ensureFor($payload);

        return $banner->id;
    }

    public function remove(int $entityId): void
    {
        throw ValidationException::withMessages(['revision' => 'The home banner cannot be deleted. Remove its slides instead.']);
    }

    public function summary(array $payload): array
    {
        $config = BannerConfig::normalise($payload);
        $media = Media::whereIn('id', collect($config['slides'])->flatMap(fn ($s) => [$s['background']['image']['media_id'], $s['background']['video']['media_id']])->filter())
            ->pluck('original_name', 'id');

        $summary = [
            'Layout' => ($config['frame'] === 'container' ? 'Aligned with the page content' : 'Full banner width')
                .'; height '.$config['height']['desktop'].' / '.$config['height']['tablet'].' / '.$config['height']['mobile'].' px (desktop / tablet / mobile)'
                .($config['scrim'] ? '; shade behind the slide controls' : ''),
        ];

        foreach ($config['slides'] as $i => $slide) {
            $bg = $slide['background'];
            $lines = ['Background: '.match ($bg['type']) {
                'image' => 'image '.($bg['image']['media_id'] ? ($media[$bg['image']['media_id']] ?? 'missing file') : basename((string) $bg['image']['path'])),
                'video' => 'video '.($media[$bg['video']['media_id']] ?? 'missing file'),
                default => 'colour '.Color::css($bg['color']),
            }.($bg['alt'] !== '' ? " (“{$bg['alt']}”)" : '')];

            foreach (BannerConfig::BREAKPOINTS as $bp) {
                $overlay = $slide['overlay'][$bp];
                $lines[] = 'Overlay, '.$bp.': '.($overlay === null ? 'same as the larger screen' : $this->describeOverlay($overlay));
            }

            foreach ($slide['elements'] as $el) {
                $t = $el['typography'];
                $lines[] = ($el['type'] === 'button' ? 'Button' : 'Text ('.$el['tag'].')').': “'.str_replace("\n", ' / ', $el['content']).'”'
                    .($el['type'] === 'button' ? ' → '.$el['href'].($el['newTab'] ? ' (new tab)' : '') : '')
                    .'; '.(BannerFonts::FAMILIES[$t['fontFamily']][0] ?? $t['fontFamily']).' '.$t['fontWeight'].', '.$t['fontSize']['desktop'].' px'
                    .'; '.($el['position']['desktop'] ? 'placed at '.$el['position']['desktop']['x'].'%, '.$el['position']['desktop']['y'].'%' : 'automatic layout')
                    .($el['visible'] ? '' : '; hidden');
            }

            $summary['Slide '.($i + 1).': '.$slide['name']] = $lines;
        }

        return $summary;
    }

    private function describeOverlay(array $overlay): string
    {
        if (! $overlay['enabled']) {
            return 'off';
        }

        $text = match ($overlay['mode']) {
            'solid' => 'solid '.Color::css($overlay['solid']['color']),
            'radial-gradient' => 'radial gradient',
            default => 'linear gradient at '.$overlay['gradient']['angle'].'°',
        };
        if ($overlay['mode'] !== 'solid') {
            $text .= ' ('.collect($overlay['gradient']['stops'])->map(fn ($s) => Color::css($s['color']).' at '.$s['position'].'%')->implode(', ').')';
        }

        return $text.($overlay['opacity'] < 1 ? ', opacity '.round($overlay['opacity'] * 100).'%' : '')
            .($overlay['blendMode'] !== 'normal' ? ', blend '.$overlay['blendMode'] : '');
    }

    public function editUrl(?int $entityId, ?int $revisionId = null): string
    {
        return $revisionId ? route('studio.banner.revision', $revisionId) : route('studio.banner.edit');
    }

    public function publicUrl(int $entityId): ?string
    {
        return route('home');
    }

    public function hasPreview(): bool
    {
        return true;
    }

    public function preview(array $payload, ?int $entityId): ?Renderable
    {
        return view('home', ['isPreview' => true] + HomePage::data(null, BannerConfig::normalise($payload)));
    }
}
