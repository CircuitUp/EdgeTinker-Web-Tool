<?php

namespace Tests\Unit\Banner;

use App\Support\Banner\BannerConfig;
use App\Support\Banner\Color;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BannerConfigTest extends TestCase
{
    private function validate(array $config): array
    {
        $config = BannerConfig::normalise($config);

        return Validator::make($config, BannerConfig::rules(), BannerConfig::messages())->errors()->toArray();
    }

    public function test_colours_are_parsed_from_every_supported_format(): void
    {
        $this->assertSame(['r' => 46, 'g' => 16, 'b' => 101, 'a' => 1], Color::parse('#2E1065'));
        $this->assertSame(['r' => 255, 'g' => 0, 'b' => 0, 'a' => 0.502], Color::parse('#ff000080'));
        $this->assertSame(['r' => 17, 'g' => 34, 'b' => 51, 'a' => 1], Color::parse('#123'));
        $this->assertSame(['r' => 1, 'g' => 2, 'b' => 3, 'a' => 0.25], Color::parse('rgba(1, 2, 3, 0.25)'));
        $this->assertSame(['r' => 1, 'g' => 2, 'b' => 3, 'a' => 0.5], Color::parse('rgb(1 2 3 / 50%)'));
        $this->assertSame(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0], Color::parse('transparent'));
        $this->assertSame(['r' => 255, 'g' => 0, 'b' => 0, 'a' => 0], Color::parse(['r' => 300, 'g' => -5, 'b' => 0, 'a' => -1]), 'Values are clamped.');
        $this->assertNull(Color::parse('red; background: url(x)'));
    }

    public function test_normalise_fills_defaults_and_drops_unknown_or_unsafe_values(): void
    {
        $config = BannerConfig::normalise([
            'version' => 99, 'frame' => 'weird', 'height' => ['desktop' => 99999], 'evil' => 'x',
            'slides' => [[
                'overlay' => ['desktop' => ['mode' => 'conic', 'opacity' => 7, 'blendMode' => 'difference']],
                'elements' => [
                    ['type' => 'text', 'content' => "<script>alert(1)</script>Hello\r\n\r\n\r\nWorld", 'tag' => 'h1',
                        'typography' => ['fontFamily' => 'Comic Sans', 'fontWeight' => 950, 'color' => 'url(evil)', 'fontSize' => ['desktop' => 1000]]],
                    ['type' => 'video', 'content' => 'x'],
                ],
            ]],
        ]);

        $this->assertSame(1, $config['version']);
        $this->assertSame('container', $config['frame']);
        $this->assertSame(1200, $config['height']['desktop']);
        $this->assertArrayNotHasKey('evil', $config);

        $overlay = $config['slides'][0]['overlay']['desktop'];
        $this->assertSame(['solid', 1, 'normal'], [$overlay['mode'], $overlay['opacity'], $overlay['blendMode']]);
        $this->assertCount(2, $overlay['gradient']['stops'], 'A gradient always has two stops.');

        [$text, $second] = $config['slides'][0]['elements'];
        $this->assertSame("alert(1)Hello\n\nWorld", $text['content'], 'Markup is stripped; line breaks are kept (at most one blank line).');
        $this->assertSame('p', $text['tag'], 'Only h2, h3 and p are allowed: the page keeps its single h1.');
        $this->assertSame('inter', $text['typography']['fontFamily']);
        $this->assertSame(900, $text['typography']['fontWeight']);
        $this->assertSame(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 1], $text['typography']['color']);
        $this->assertSame(200, $text['typography']['fontSize']['desktop']);
        $this->assertSame('text', $second['type']);
        $this->assertNotSame($text['id'], $second['id']);
    }

    public function test_breakpoints_inherit_from_larger_ones(): void
    {
        $values = ['desktop' => 48, 'tablet' => null, 'mobile' => null];
        $this->assertSame([48, 48, 48], [BannerConfig::resolve($values, 'desktop'), BannerConfig::resolve($values, 'tablet'), BannerConfig::resolve($values, 'mobile')]);

        $values['tablet'] = 36;
        $this->assertSame(36, BannerConfig::resolve($values, 'mobile'));

        $values['mobile'] = 30;
        $this->assertSame(30, BannerConfig::resolve($values, 'mobile'));
        $this->assertSame(36, BannerConfig::resolve($values, 'tablet'));
    }

    public function test_any_colour_may_be_fully_transparent(): void
    {
        $config = BannerConfig::normalise(['slides' => [[
            'overlay' => ['desktop' => ['mode' => 'linear-gradient', 'gradient' => ['stops' => [
                ['color' => ['r' => 46, 'g' => 16, 'b' => 101, 'a' => 0], 'position' => 0],
                ['color' => 'rgba(0,0,0,0)', 'position' => 100],
            ]]]],
            'elements' => [['type' => 'button', 'content' => 'Go', 'href' => '/x', 'button' => ['bg' => 'transparent', 'border' => '#ffffff00']]],
        ]]]);

        $this->assertSame(0, $config['slides'][0]['overlay']['desktop']['gradient']['stops'][0]['color']['a']);
        $this->assertSame(0, $config['slides'][0]['overlay']['desktop']['gradient']['stops'][1]['color']['a']);
        $this->assertSame(0, $config['slides'][0]['elements'][0]['button']['bg']['a']);
        $this->assertSame(0, $config['slides'][0]['elements'][0]['button']['border']['a']);
    }

    public function test_validation_catches_what_cannot_be_repaired(): void
    {
        $this->assertArrayHasKey('slides', $this->validate(['slides' => []]), 'At least one slide.');

        $errors = $this->validate(['slides' => [['elements' => [
            ['type' => 'text', 'content' => '   '],
            ['type' => 'button', 'content' => 'A', 'href' => 'javascript:alert(1)'],
            ['type' => 'button', 'content' => 'B', 'href' => '//evil.example'],
            ['type' => 'button', 'content' => 'C', 'href' => ''],
            ['type' => 'button', 'content' => 'D', 'href' => 'data:text/html,x'],
        ]]]]);

        $this->assertArrayHasKey('slides.0.elements.0.content', $errors);
        foreach ([1, 2, 3, 4] as $i) {
            $this->assertArrayHasKey("slides.0.elements.$i.href", $errors, "Button $i has an unsafe or missing link.");
        }

        $ok = $this->validate(['slides' => [['elements' => [
            ['type' => 'button', 'content' => 'A', 'href' => '/products'],
            ['type' => 'button', 'content' => 'B', 'href' => 'https://example.com/a?b=c'],
            ['type' => 'button', 'content' => 'C', 'href' => '#contact'],
            ['type' => 'button', 'content' => 'D', 'href' => 'mailto:hello@example.com'],
            ['type' => 'button', 'content' => 'E', 'href' => 'tel:+94 11 234 5678'],
        ]]]]);
        $this->assertSame([], $ok);
    }

    public function test_unreadable_stored_data_falls_back_to_an_empty_banner(): void
    {
        $this->assertSame(BannerConfig::empty(), BannerConfig::fromStored('not json'));
        $this->assertSame(BannerConfig::empty(), BannerConfig::fromStored(['version' => 2, 'slides' => [['x' => 1]]]));
        $this->assertSame(BannerConfig::empty(), BannerConfig::fromStored(null));
        $this->assertCount(1, BannerConfig::fromStored(json_encode(['version' => 1, 'slides' => [['elements' => 'oops']]]))['slides'],
            'A config with damaged parts is repaired rather than thrown away.');
    }

    public function test_reset_design_keeps_words_and_background_but_restores_the_original_look(): void
    {
        $config = BannerConfig::fromLegacySlides([['heading' => 'Keep me', 'sub_text' => 'And me', 'button_label' => 'Go', 'button_url' => '/x', 'image_path' => 'placeholders/a.svg']]);
        $config['slides'][0]['overlay']['desktop']['enabled'] = false;
        $config['slides'][0]['elements'][0]['typography']['fontSize']['desktop'] = 99;
        $config['slides'][0]['elements'][0]['position']['desktop'] = ['x' => 10, 'y' => 10, 'ax' => 0, 'ay' => 0];

        $reset = BannerConfig::resetDesign($config);
        $slide = $reset['slides'][0];

        $this->assertSame(['Keep me', 'And me', 'Go'], array_column($slide['elements'], 'content'));
        $this->assertSame('placeholders/a.svg', $slide['background']['image']['path']);
        $this->assertSame($config['slides'][0]['id'], $slide['id']);
        $this->assertTrue($slide['overlay']['desktop']['enabled']);
        $this->assertSame(48, $slide['elements'][0]['typography']['fontSize']['desktop']);
        $this->assertNull($slide['elements'][0]['position']['desktop']);
    }
}
