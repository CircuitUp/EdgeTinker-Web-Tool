<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Editable page content (Home, Services, Contact), the wiki and contact-form messages. */
return new class extends Migration
{
    public function up(): void
    {
        // One row per editable page. `content` holds the page's structured fields as JSON.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();      // home | services | contact
            $table->json('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
        });

        /*
         * Wiki pages belong to a product. The wiki's tree (category → product →
         * pages) is derived from the catalogue, so a product's documentation
         * always sits under the product's current category.
         */
        Schema::create('wiki_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('body');                   // Markdown
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'slug']);
            $table->index(['product_id', 'sort_order']);

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'body'], 'wiki_pages_search_fulltext');
            }
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('company')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('wiki_pages');
        Schema::dropIfExists('pages');
    }
};
