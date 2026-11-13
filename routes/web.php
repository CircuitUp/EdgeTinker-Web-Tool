<?php

use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductListingController;
use App\Http\Controllers\SearchSuggestController;
use App\Http\Controllers\StyleguideController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Catalogue: one shared listing layout for all products, a category, and search results.
Route::get('/products', [ProductListingController::class, 'index'])->name('products.index');
Route::get('/products/{category}', [ProductListingController::class, 'category'])->name('products.category');
Route::get('/search', [ProductListingController::class, 'search'])->name('search');
Route::get('/search/suggest', SearchSuggestController::class)->middleware('throttle:90,1')->name('search.suggest');

// Dedicated product page.
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('products.show');

// Currency toggle (USD / LKR), remembered in a cookie.
Route::post('/currency', CurrencyController::class)->name('currency.switch');

// Design system reference: local and staging only, never in production.
if (! app()->isProduction()) {
    Route::get('/styleguide', StyleguideController::class)->name('styleguide');
}

/*
| Pages that arrive in later phases. The routes exist now so the header,
| footer and home page links resolve; each shows a "coming in phase N" notice.
*/
Route::view('/services', 'coming-soon', ['title' => 'Services', 'phase' => 4])->name('services');
Route::view('/contact', 'coming-soon', ['title' => 'Contact Us', 'phase' => 4])->name('contact');
Route::view('/wiki', 'coming-soon', ['title' => 'Wiki', 'phase' => 4])->name('wiki.index');
