<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Content Studio: accounts, approval workflow, audit trail and media library. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('creator')->after('password');     // admin | creator
            $table->string('status', 20)->default('pending')->after('role');       // pending | active | suspended
            $table->text('two_factor_secret')->nullable()->after('status');        // encrypted
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret'); // encrypted JSON
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_confirmed_at');

            $table->index(['role', 'status']);
        });

        /*
         * Every content change is a revision. The main tables (products,
         * categories, …) only ever hold approved content; a revision's payload
         * is copied into them, in a transaction, when the admin approves it.
         */
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40);                   // product | category | tag | wiki_page | page
            $table->unsignedBigInteger('entity_id')->nullable();  // null until a new item is first approved
            $table->string('action', 10);                         // create | update | delete
            $table->string('status', 12)->default('draft');       // draft | submitted | approved | rejected
            $table->string('title');                              // display name of the item, for lists
            $table->json('payload')->nullable();                  // proposed content (null for delete)
            $table->json('previous')->nullable();                 // live content just before approval (history / restore)
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();                  // reviewer's comment (required on rejection)
            $table->boolean('needs_rebase')->default(false);      // another change to the same item was approved meanwhile
            $table->string('rebase_note', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'submitted_at']);
            $table->index(['author_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();              // kept even if the account is deleted
            $table->string('event', 60)->index();                 // e.g. revision.approved, user.suspended
            $table->string('description', 500);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12)->index();                  // image | document
            $table->string('path');                               // on the public disk
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');                   // bytes
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('variants')->nullable();                 // {"400": "media/…-400.webp", …} once generated
            $table->string('alt')->default('');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->after('product_id')->constrained('media')->nullOnDelete();
        });

        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20)->default('other');         // datasheet | schematic | pinout | model_3d | firmware | other
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_documents');
        Schema::table('product_images', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_id'));
        Schema::dropIfExists('media');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('revisions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropColumn(['role', 'status', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'last_login_at']);
        });
    }
};
