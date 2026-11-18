<?php

namespace Tests\Feature\Banner;

use App\Models\Banner;
use App\Models\Media;
use App\Support\Banner\BannerConfig;
use App\Support\Banner\BannerPresenter;
use App\Support\Banner\BannerStyles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The old banner_slides rows become a banner that looks exactly like the original hard-coded design. */
class BannerMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_18_000100_create_banners_table.php');
    }

    public function test_existing_slides_are_converted_to_the_default_design(): void
    {
        $media = Media::create(['kind' => 'image', 'path' => 'media/2026/11/hero.jpg', 'original_name' => 'hero.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'width' => 1920, 'height' => 900]);

        // Go back to the state before the migration: the old table with two active slides and one switched off.
        $this->migration()->down();
        DB::table('banner_slides')->insert([
            ['heading' => 'Second', 'sub_text' => null, 'button_label' => null, 'button_url' => null, 'image_path' => null, 'image_alt' => '', 'sort_order' => 2, 'is_active' => true],
            ['heading' => 'First', 'sub_text' => 'Sub', 'button_label' => 'Go', 'button_url' => '/products', 'image_path' => 'media/2026/11/hero.jpg', 'image_alt' => 'A board', 'sort_order' => 1, 'is_active' => true],
            ['heading' => 'Off', 'sub_text' => null, 'button_label' => null, 'button_url' => null, 'image_path' => null, 'image_alt' => '', 'sort_order' => 3, 'is_active' => false],
        ]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('banner_slides'));
        $config = Banner::home()->design();
        $this->assertCount(2, $config['slides'], 'Inactive slides are not carried over.');

        [$first, $second] = $config['slides'];
        $this->assertSame(['First', 'Sub', 'Go'], array_column($first['elements'], 'content'));
        $this->assertSame(['h2', 'p', null], array_column($first['elements'], 'tag'));
        $this->assertSame('/products', $first['elements'][2]['href']);
        $this->assertSame(['media_id' => $media->id, 'path' => null], $first['background']['image'], 'Uploaded images are linked to their media library entry.');
        $this->assertSame('A board', $first['background']['alt']);
        $this->assertSame(['Second'], array_column($second['elements'], 'content'), 'Empty sub-text and buttons are not created.');

        // The original design: heading 30/36/48 px, sub-text 18/20 px, white with a soft shadow, laid out automatically.
        $heading = $first['elements'][0];
        $this->assertSame(['desktop' => 48, 'tablet' => 36, 'mobile' => 30], $heading['typography']['fontSize']);
        $this->assertSame(['desktop' => null, 'tablet' => null, 'mobile' => null], $heading['position']);
        $this->assertSame('0px 2px 16px rgba(15,23,42,0.55)', str($heading = BannerStyles::element($heading, 0))->after('text-shadow:')->before(';')->toString());
        $this->assertSame(['desktop' => 576, 'tablet' => 512, 'mobile' => 480], $config['height']);
    }

    public function test_rolling_back_restores_the_old_table(): void
    {
        Banner::home()->update(['config' => BannerConfig::fromLegacySlides([['heading' => 'Back again', 'sub_text' => 'Sub', 'button_label' => 'Go', 'button_url' => '/x', 'image_path' => 'placeholders/slide-1.svg']])]);

        $this->migration()->down();

        $this->assertFalse(Schema::hasTable('banners'));
        $row = DB::table('banner_slides')->sole();
        $this->assertSame(['Back again', 'Sub', 'Go', '/x', 'placeholders/slide-1.svg'], [$row->heading, $row->sub_text, $row->button_label, $row->button_url, $row->image_path]);

        $this->migration()->up(); // leave the schema as the other tests expect
        $this->assertSame('Back again', Banner::home()->design()['slides'][0]['elements'][0]['content']);
    }

    public function test_a_fresh_install_has_no_banner_until_slides_are_added(): void
    {
        $this->assertSame([], Banner::home()->design()['slides']);
        $this->assertSame([], app(BannerPresenter::class)->present(Banner::home()->design())['slides']);
        $this->get('/')->assertOk()->assertDontSee('aria-roledescription="carousel"', false);
    }

    public function test_a_damaged_stored_config_does_not_break_the_home_page(): void
    {
        DB::table('banners')->where('key', 'home')->update(['config' => '{"version":1,"slides":"nonsense"}']);

        $this->get('/')->assertOk()->assertDontSee('aria-roledescription="carousel"', false);
    }

    protected function tearDown(): void
    {
        // Make sure a failed assertion above never leaves the schema half migrated for the next test.
        if (! Schema::hasTable('banners')) {
            Schema::create('banners', function (Blueprint $table) {
                $table->id();
                $table->string('key', 40)->unique();
                $table->json('config');
                $table->timestamps();
            });
        }

        parent::tearDown();
    }
}
