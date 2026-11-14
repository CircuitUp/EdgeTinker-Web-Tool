<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductListingController;
use App\Http\Controllers\SearchSuggestController;
use App\Http\Controllers\StyleguideController;
use App\Http\Controllers\WikiController;
use App\Support\PageContent;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Catalogue: one shared listing layout for all products, a category, and search results.
Route::get('/products', [ProductListingController::class, 'index'])->name('products.index');
Route::get('/products/{category}', [ProductListingController::class, 'category'])->name('products.category');
Route::get('/search', [ProductListingController::class, 'search'])->name('search');
Route::get('/search/suggest', SearchSuggestController::class)->middleware('throttle:90,1')->name('search.suggest');

// Dedicated product page.
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('products.show');

// Company pages. Their text is edited in Content Studio → Pages.
Route::get('/services', fn (PageContent $pages) => view('services', [
    'content' => $pages->get('services'),
    'seo' => $pages->seo('services'),
]))->name('services');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:10,1')->name('contact.send');

// Wiki (same app, same domain).
Route::get('/wiki', [WikiController::class, 'index'])->name('wiki.index');
Route::get('/wiki/search', [WikiController::class, 'search'])->name('wiki.search');
Route::get('/wiki/{category}/{product}', [WikiController::class, 'product'])->name('wiki.product');
Route::get('/wiki/{category}/{product}/{page}', [WikiController::class, 'show'])->name('wiki.show');

// Currency toggle (USD / LKR), remembered in a cookie.
Route::post('/currency', CurrencyController::class)->name('currency.switch');

// Design system reference: local and staging only, never in production.
if (! app()->isProduction()) {
    Route::get('/styleguide', StyleguideController::class)->name('styleguide');
}
