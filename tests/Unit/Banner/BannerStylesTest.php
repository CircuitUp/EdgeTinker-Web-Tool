<?php

namespace Tests\Unit\Banner;

use App\Support\Banner\BannerConfig;
use App\Support\Banner\BannerFonts;
use App\Support\Banner\BannerStyles;
use App\Support\Banner\Color;
use Tests\TestCase;

/**
 * CSS generation for the banner. The cases are also written to
 * tests/fixtures/banner/styles.json, which tests/js/banner-styles.test.mjs
 * runs against the editor's JavaScript copy (resources/js/banner/styles.js),
 * so the editor canvas and the live site cannot drift apart.
 *
 * After an intended change to the CSS: UPDATE_BANNER_FIXTURES=1 php artisan test --filter BannerStylesTest
 */
class BannerStylesTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../../fixtures/banner/styles.json';

    public function test_overlay_css_for_each_mode(): void
    {
        $purple = fn (float $a) => BannerConfig::primary900($a);

        $this->assertSame('none', BannerStyles::overlayBackground(BannerConfig::overlay(enabled: false)),
            'A switched-off overlay draws nothing.');
        $this->assertSame('rgba(46,16,101,0.65)', BannerStyles::overlayBackground(BannerConfig::overlay('solid', $purple(0.65))));
        $this->assertSame('rgba(0,0,0,0)', BannerStyles::overlayBackground(BannerConfig::overlay('solid', Color::make(0, 0, 0, 0))),
            'A fully transparent colour is allowed.');

        $linear = BannerConfig::overlay('linear-gradient', gradient: ['angle' => 135, 'shape' => 'ellipse', 'center' => ['x' => 50, 'y' => 50], 'stops' => [
            ['color' => Color::make(255, 0, 0, 0), 'position' => 80],   // listed out of order on purpose
            ['color' => $purple(0), 'position' => 10],
        ]]);
        $this->assertSame('linear-gradient(135deg,rgba(46,16,101,0) 10%,rgba(255,0,0,0) 80%)', BannerStyles::overlayBackground($linear),
            'Stops are sorted by position and both may be fully transparent.');

        $radial = BannerConfig::overlay('radial-gradient', gradient: ['angle' => 0, 'shape' => 'circle', 'center' => ['x' => 30, 'y' => 70.5], 'stops' => [
            ['color' => $purple(0.8), 'position' => 0], ['color' => $purple(0), 'position' => 100],
        ]]);
        $this->assertSame('radial-gradient(circle at 30% 70.5%,rgba(46,16,101,0.8) 0%,rgba(46,16,101,0) 100%)', BannerStyles::overlayBackground($radial));
    }

    public function test_the_default_overlay_reproduces_the_original_design(): void
    {
        $slide = BannerConfig::normalise(['slides' => [BannerConfig::slide('Hello')]])['slides'][0];
        $css = BannerStyles::overlay($slide);

        // Desktop: the original bg-linear-to-r from-primary-900/90 from-15% via-primary-900/60 via-45% to-transparent to-70%.
        $this->assertStringContainsString('--ov-d:linear-gradient(90deg,rgba(46,16,101,0.9) 15%,rgba(46,16,101,0.6) 45%,rgba(46,16,101,0) 70%)', $css);
        // Tablet and mobile: the original even bg-primary-900/65 tint (mobile inherits from tablet).
        $this->assertStringContainsString('--ov-t:rgba(46,16,101,0.65)', $css);
        $this->assertStringContainsString('--ov-m:rgba(46,16,101,0.65)', $css);
    }

    public function test_overlay_opacity_is_zero_when_off(): void
    {
        $slide = BannerConfig::normalise(['slides' => [BannerConfig::slide('Hello')]])['slides'][0];
        $slide['overlay']['desktop']['enabled'] = false;

        $this->assertStringContainsString('--ov-d:none;--ovo-d:0;', BannerStyles::overlay($slide));
    }

    public function test_element_layout_inherits_from_larger_breakpoints(): void
    {
        $el = BannerConfig::normalise(['slides' => [['elements' => [[
            'type' => 'text', 'content' => 'Hi',
            'position' => ['desktop' => ['x' => 50, 'y' => 40, 'ax' => 50, 'ay' => 0], 'tablet' => null, 'mobile' => ['x' => 5, 'y' => 60]],
            'width' => ['desktop' => 40, 'tablet' => null, 'mobile' => 90],
            'rotation' => -5,
            'typography' => ['fontSize' => ['desktop' => 64, 'tablet' => null, 'mobile' => 28]],
        ]]]]])['slides'][0]['elements'][0];

        $css = BannerStyles::element($el, 2);

        $this->assertStringContainsString('--pos-d:absolute;--l-d:50%;--t-d:40%;--w-d:40%;--tf-d:translate(-50%,0%) rotate(-5deg);--mt-d:0px;--fs-d:64px', $css);
        $this->assertStringContainsString('--pos-t:absolute;--l-t:50%;--t-t:40%;--w-t:40%', $css, 'Tablet inherits the desktop position.');
        $this->assertStringContainsString('--pos-m:absolute;--l-m:5%;--t-m:60%;--w-m:90%;--tf-m:translate(0%,0%) rotate(-5deg)', $css);
        $this->assertStringContainsString('--fs-t:64px', $css);
        $this->assertStringContainsString('--fs-m:28px', $css);
        $this->assertStringContainsString('z-index:3', $css);
    }

    public function test_elements_without_a_position_flow_in_the_original_layout(): void
    {
        $config = BannerConfig::fromLegacySlides([['heading' => 'H', 'sub_text' => 'S', 'button_label' => 'Go', 'button_url' => '/products']]);
        [$heading, $sub, $button] = $config['slides'][0]['elements'];

        $this->assertStringContainsString('--pos-d:relative;--l-d:auto;--t-d:auto;--w-d:auto;--tf-d:rotate(0deg);--mt-d:0px;--fs-d:48px;--lh-d:1.1', BannerStyles::element($heading, 0));
        $this->assertStringContainsString('--mt-m:16px;--fs-m:18px;--lh-m:1.556', BannerStyles::element($sub, 1));
        $this->assertStringContainsString('--mt-d:32px', BannerStyles::element($button, 2));
        $this->assertStringContainsString('--bg:rgba(255,255,255,1);--bd:rgba(255,255,255,0);--fg-h:rgba(46,16,101,1);--bg-h:rgba(245,243,255,1)', BannerStyles::element($button, 2));
    }

    public function test_shared_fixtures_match(): void
    {
        $cases = $this->cases();

        if (getenv('UPDATE_BANNER_FIXTURES')) {
            @mkdir(dirname(self::FIXTURE), 0777, true);
            file_put_contents(self::FIXTURE, json_encode([
                'note' => 'Generated by tests/Unit/Banner/BannerStylesTest.php. Checked by PHP and by tests/js/banner-styles.test.mjs.',
                'fontStacks' => collect(BannerFonts::catalogue())->pluck('stack', 'key'),
                'cases' => $cases,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        }

        $fixture = json_decode(file_get_contents(self::FIXTURE), true);
        $this->assertSame(collect(BannerFonts::catalogue())->pluck('stack', 'key')->all(), $fixture['fontStacks']);
        $this->assertSame($cases, $fixture['cases'], 'PHP output changed. If intended, regenerate the fixture (see the class comment).');
    }

    /** Inputs and the CSS PHP produces for them. */
    private function cases(): array
    {
        $config = BannerConfig::fromLegacySlides([['heading' => 'Heading', 'sub_text' => 'Sub-text', 'button_label' => 'Go', 'button_url' => '/products']]);
        $config['height'] = ['desktop' => 620, 'tablet' => 500.5, 'mobile' => 420];
        // Fixed ids, so the fixture does not change from run to run.
        $config['slides'][0]['id'] = 'sdefault';
        foreach ($config['slides'][0]['elements'] as $i => $el) {
            $config['slides'][0]['elements'][$i]['id'] = 'edefault'.$i;
        }
        $config = BannerConfig::normalise($config);

        $custom = BannerConfig::normalise(['slides' => [[
            'id' => 'scustom',
            'background' => ['type' => 'color', 'color' => '#10203080', 'position' => ['x' => 20, 'y' => 75.25], 'size' => 'contain'],
            'overlay' => [
                'desktop' => ['mode' => 'radial-gradient', 'opacity' => 0.5, 'blendMode' => 'multiply', 'gradient' => ['shape' => 'circle', 'center' => ['x' => 25, 'y' => 75],
                    'stops' => [['color' => 'rgba(0,0,0,0)', 'position' => 60], ['color' => '#ff8800', 'position' => 5], ['color' => 'transparent', 'position' => 100]]]],
                'tablet' => ['enabled' => false],
                'mobile' => ['mode' => 'linear-gradient', 'gradient' => ['angle' => 180, 'stops' => [['color' => '#000000', 'position' => 0], ['color' => 'rgba(0,0,0,0)', 'position' => 100]]]],
            ],
            'elements' => [
                ['id' => 'ecustom0', 'type' => 'text', 'content' => 'Positioned', 'tag' => 'h3',
                    'position' => ['desktop' => ['x' => 50, 'y' => 50, 'ax' => 50, 'ay' => 50], 'tablet' => ['x' => 10.125, 'y' => 20, 'ax' => 0, 'ay' => 100], 'mobile' => null],
                    'width' => ['desktop' => 60, 'tablet' => null, 'mobile' => 100], 'rotation' => 12.5, 'gap' => 8,
                    'typography' => ['fontFamily' => 'playfair-display', 'fontWeight' => 900, 'italic' => true, 'underline' => true, 'strike' => true,
                        'fontSize' => ['desktop' => 72, 'tablet' => 54, 'mobile' => null], 'lineHeight' => ['desktop' => 0.95, 'tablet' => null, 'mobile' => 1.05],
                        'letterSpacing' => 0.1, 'textAlign' => 'center', 'textTransform' => 'uppercase', 'color' => 'rgba(255,200,0,0.75)', 'shadow' => null]],
                ['id' => 'ecustom1', 'type' => 'button', 'content' => 'Shop', 'href' => 'https://example.com', 'newTab' => true, 'icon' => 'external',
                    'button' => ['bg' => 'rgba(0,0,0,0)', 'text' => '#ffffff', 'border' => '#ffffff', 'borderWidth' => 2, 'radius' => 999, 'px' => 30, 'py' => 14,
                        'hover' => ['bg' => '#ffffff', 'text' => '#2e1065', 'border' => '#ffffff'], 'shadow' => 'lg'],
                    'typography' => ['fontFamily' => 'unknown-font', 'shadow' => ['x' => 1, 'y' => -2, 'blur' => 3.5, 'color' => '#00000080']]],
            ],
        ]]]);

        $cases = [['name' => 'banner heights', 'kind' => 'banner', 'input' => $config, 'expected' => BannerStyles::banner($config)]];

        foreach (['default' => $config['slides'][0], 'custom' => $custom['slides'][0]] as $name => $slide) {
            $cases[] = ['name' => "$name slide overlay", 'kind' => 'overlay', 'input' => $slide, 'expected' => BannerStyles::overlay($slide)];
            $cases[] = ['name' => "$name slide background", 'kind' => 'background', 'input' => $slide, 'expected' => BannerStyles::background($slide)];
            foreach ($slide['elements'] as $layer => $el) {
                $cases[] = ['name' => "$name element $layer", 'kind' => 'element', 'input' => $el, 'layer' => $layer, 'expected' => BannerStyles::element($el, $layer)];
            }
        }

        foreach (['desktop', 'tablet', 'mobile'] as $bp) {
            $overlay = BannerConfig::resolve($custom['slides'][0]['overlay'], $bp);
            $cases[] = ['name' => "overlay background $bp", 'kind' => 'overlayBackground', 'input' => $overlay, 'expected' => BannerStyles::overlayBackground($overlay)];
        }

        return $cases;
    }
}
