<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue tables. These hold ONLY approved, live content: pending changes
 * live in `revisions` (added with Content Studio) and are copied in on approval.
 *
 * Phase 2 commerce (carts, orders, customers) is expected to arrive as new
 * tables that reference `products.id`; nothing here needs to change for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 64)->unique();
            $table->string('short_description', 500);
            $table->longText('description')->nullable();
            $table->json('key_features')->nullable();
            $table->decimal('price_usd', 10, 2)->nullable();
            $table->decimal('price_lkr', 12, 2)->nullable();
            $table->string('stock_status', 20)->default('in_stock')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();

            $table->index('price_usd');
            $table->index('price_lkr');
            $table->index('created_at');

            // SQLite (used by non-search tests) has no FULLTEXT support.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['name', 'sku', 'short_description', 'description'], 'products_search_fulltext');
            }
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->default('');
            $table->boolean('is_thumbnail')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('value', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_tag', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

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

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('banner_slides');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('product_specs');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
