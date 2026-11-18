<?php

use App\Http\Controllers\Studio\Auth\EmailVerificationController;
use App\Http\Controllers\Studio\Auth\LoginController;
use App\Http\Controllers\Studio\Auth\PasswordResetController;
use App\Http\Controllers\Studio\Auth\RegisterController;
use App\Http\Controllers\Studio\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Studio\BannerPreviewController;
use App\Http\Controllers\Studio\PreviewController;
use App\Livewire\Studio;
use Illuminate\Support\Facades\Route;

/*
| Content Studio (/contentcreator). Reachable only by typing the address:
| nothing on the public site links here and it is excluded from the sitemap.
| Every response carries noindex headers (StudioHeaders middleware).
| Loaded from bootstrap/app.php with the "web" and "studio.headers" middleware.
*/

// ── Signing in and registering (guests) ─────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/two-factor', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:12,1'])->name('verification.verify');
Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1')->name('verification.resend');

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// ── The studio itself (signed in, verified, active) ─────────────────────────
Route::middleware('studio')->group(function () {
    Route::livewire('/', Studio\Dashboard::class)->name('dashboard');

    Route::livewire('/products', Studio\Products\Index::class)->name('products.index');
    Route::livewire('/products/new', Studio\Products\Form::class)->name('products.create');
    Route::livewire('/products/{product}/edit', Studio\Products\Form::class)->whereNumber('product')->name('products.edit');
    Route::livewire('/products/revision/{revision}', Studio\Products\Form::class)->whereNumber('revision')->name('products.revision');

    Route::livewire('/categories', Studio\Categories\Index::class)->name('categories.index');
    Route::livewire('/categories/new', Studio\Categories\Form::class)->name('categories.create');
    Route::livewire('/categories/{category}/edit', Studio\Categories\Form::class)->whereNumber('category')->name('categories.edit');
    Route::livewire('/categories/revision/{revision}', Studio\Categories\Form::class)->whereNumber('revision')->name('categories.revision');

    Route::livewire('/tags', Studio\Tags\Index::class)->name('tags.index');

    // Home, Services and Contact page content.
    Route::livewire('/pages', Studio\Pages\Index::class)->name('pages.index');
    Route::livewire('/pages/revision/{revision}', Studio\Pages\Form::class)->whereNumber('revision')->name('pages.revision');
    Route::livewire('/pages/{page}', Studio\Pages\Form::class)->whereIn('page', ['home', 'services', 'contact'])->name('pages.edit');

    // Visual editor for the home page banner.
    Route::livewire('/banner', Studio\Banner\Editor::class)->name('banner.edit');
    Route::livewire('/banner/revision/{revision}', Studio\Banner\Editor::class)->whereNumber('revision')->name('banner.revision');
    Route::post('/banner/preview', BannerPreviewController::class)->middleware('throttle:60,1')->name('banner.preview');

    // Wiki tree manager and Markdown editor.
    Route::livewire('/wiki', Studio\Wiki\Index::class)->name('wiki.index');
    Route::livewire('/wiki/new', Studio\Wiki\Form::class)->name('wiki.create');
    Route::livewire('/wiki/{page}/edit', Studio\Wiki\Form::class)->whereNumber('page')->name('wiki.edit');
    Route::livewire('/wiki/revision/{revision}', Studio\Wiki\Form::class)->whereNumber('revision')->name('wiki.revision');
    Route::livewire('/media', Studio\Media\Library::class)->name('media.index');

    // A creator's own submissions (the admin sees everyone's).
    Route::livewire('/submissions', Studio\Revisions\Index::class)->name('revisions.index');
    Route::livewire('/submissions/{revision}', Studio\Revisions\Show::class)->whereNumber('revision')->name('revisions.show');
    Route::get('/submissions/{revision}/preview', PreviewController::class)->whereNumber('revision')->name('revisions.preview');
    Route::livewire('/history/{type}/{id}', Studio\Revisions\History::class)->whereNumber('id')->name('history');

    Route::livewire('/account', Studio\Account::class)->name('account');

    // ── Content Admin only ──────────────────────────────────────────────────
    Route::middleware('studio:admin')->group(function () {
        Route::livewire('/review', Studio\Revisions\Index::class)->name('review.index');
        Route::livewire('/review/{revision}', Studio\Revisions\Show::class)->whereNumber('revision')->name('review.show');
        Route::livewire('/messages', Studio\Messages\Index::class)->name('messages.index');
        Route::livewire('/settings', Studio\Settings::class)->name('settings');
        Route::livewire('/users', Studio\Users\Index::class)->name('users.index');
        Route::livewire('/audit-log', Studio\AuditLogIndex::class)->name('audit.index');
    });
});
