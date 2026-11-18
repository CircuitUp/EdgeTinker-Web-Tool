<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_builds_every_icon_from_the_logo(): void
    {
        $out = storage_path('framework/testing/favicons');
        File::deleteDirectory($out);
        File::ensureDirectoryExists($out);
        $real = public_path();
        $this->app->usePublicPath($out);

        try {
            $this->artisan('brand:favicons')->assertSuccessful();
        } finally {
            $this->app->usePublicPath($real);
        }

        foreach (['favicon-16x16.png' => 16, 'favicon-32x32.png' => 32, 'apple-touch-icon.png' => 180,
            'android-chrome-192x192.png' => 192, 'android-chrome-512x512.png' => 512, 'android-chrome-maskable-512x512.png' => 512] as $file => $size) {
            [$w, $h, $type] = getimagesize("$out/$file");
            $this->assertSame([$size, $size, IMAGETYPE_PNG], [$w, $h, $type], $file);
        }

        // favicon.ico holds 16, 32 and 48 px PNG images.
        $ico = file_get_contents("$out/favicon.ico");
        $this->assertSame(['reserved' => 0, 'type' => 1, 'count' => 3], unpack('vreserved/vtype/vcount', $ico));
        foreach ([16, 32, 48] as $i => $size) {
            $entry = unpack('Cwidth/Cheight/x2/vplanes/vbits/Vbytes/Voffset', substr($ico, 6 + 16 * $i, 16));
            $this->assertSame([$size, $size, 32], [$entry['width'], $entry['height'], $entry['bits']]);
            $this->assertSame("\x89PNG", substr($ico, $entry['offset'], 4));
        }

        // The Apple icon has no transparency (iOS would show black corners); small icons have rounded, transparent corners.
        $apple = imagecreatefrompng("$out/apple-touch-icon.png");
        $this->assertSame(0, imagecolorsforindex($apple, imagecolorat($apple, 0, 0))['alpha']);
        $small = imagecreatefrompng("$out/favicon-32x32.png");
        $this->assertSame(127, imagecolorsforindex($small, imagecolorat($small, 0, 0))['alpha']);

        $manifest = json_decode(file_get_contents("$out/site.webmanifest"), true);
        $this->assertSame('EdgeTinker', $manifest['name']);
        $this->assertSame('#2E1065', $manifest['theme_color']);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        File::deleteDirectory($out);
    }

    public function test_every_kind_of_page_links_the_icons(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            $this->get('/'),
            $this->get('/wiki'),
            $this->get(route('studio.login')),
            $this->actingAs($admin)->get(route('studio.dashboard')),
        ] as $response) {
            $response->assertOk()
                ->assertSee('rel="icon" href="'.asset('favicon.ico'), false)
                ->assertSee('rel="apple-touch-icon"', false)
                ->assertSee('rel="manifest"', false)
                ->assertSee('<meta name="theme-color" content="#2E1065">', false);
        }

        $this->get('/no-such-page')->assertNotFound()->assertSee('rel="icon" href="'.asset('favicon.ico'), false);
        // The bare fallback error page (used when the site itself is broken) links them too.
        $this->assertStringContainsString('<link rel="icon" href="/favicon.ico"', view('errors.500')->render());
    }

    public function test_the_icon_files_are_in_place(): void
    {
        foreach (['favicon.ico', 'favicon-16x16.png', 'favicon-32x32.png', 'apple-touch-icon.png', 'android-chrome-192x192.png', 'android-chrome-512x512.png', 'site.webmanifest'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }
}
