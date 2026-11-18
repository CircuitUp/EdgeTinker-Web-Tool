<?php

use App\Support\Banner\BannerConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the banner_slides table with a single banner design document.
 *
 * The existing slides are converted to a BannerConfig that reproduces the
 * old hard-coded design exactly (overlay, typography and layout), so the
 * live home page looks the same until someone edits the banner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->json('config');
            $table->timestamps();
        });

        $rows = Schema::hasTable('banner_slides')
            ? DB::table('banner_slides')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        $config = BannerConfig::fromLegacySlides($rows,
            fn (string $path) => DB::table('media')->where('path', $path)->where('kind', 'image')->value('id'));

        DB::table('banners')->insert([
            'key' => 'home',
            'config' => json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::dropIfExists('banner_slides');
    }

    public function down(): void
    {
        Schema::create('banner_slides', function (Blueprint $table) {
            $table->id();
            $table->string('heading');
            $table->string('sub_text', 500)->nullable();
            $table->string('button_label')->nullable();
            $table->string('button_url')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->default('');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bring back what the old table can hold: the words, link and image of each slide.
        $config = BannerConfig::fromStored(DB::table('banners')->where('key', 'home')->value('config'));
        foreach ($config['slides'] as $order => $slide) {
            $texts = collect($slide['elements'])->where('type', 'text')->values();
            $button = collect($slide['elements'])->firstWhere('type', 'button');
            $image = $slide['background']['image'];

            DB::table('banner_slides')->insert([
                'heading' => mb_substr($texts[0]['content'] ?? $slide['name'], 0, 255),
                'sub_text' => isset($texts[1]) ? mb_substr($texts[1]['content'], 0, 500) : null,
                'button_label' => $button['content'] ?? null,
                'button_url' => $button['href'] ?? null,
                'image_path' => $image['media_id'] ? DB::table('media')->where('id', $image['media_id'])->value('path') : $image['path'],
                'image_alt' => $slide['background']['alt'],
                'sort_order' => $order + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('banners');
    }
};
