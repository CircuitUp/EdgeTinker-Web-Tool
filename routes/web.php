<?php

use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StyleguideController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

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
Route::view('/products', 'coming-soon', ['title' => 'Products', 'phase' => 2])->name('products.index');
Route::view('/products/{category}', 'coming-soon', ['title' => 'Products', 'phase' => 2])->name('products.category');
Route::view('/product/{slug}', 'coming-soon', ['title' => 'Product', 'phase' => 2])->name('products.show');
Route::view('/search', 'coming-soon', ['title' => 'Search', 'phase' => 2])->name('search');
Route::view('/services', 'coming-soon', ['title' => 'Services', 'phase' => 4])->name('services');
Route::view('/contact', 'coming-soon', ['title' => 'Contact Us', 'phase' => 4])->name('contact');
Route::view('/wiki', 'coming-soon', ['title' => 'Wiki', 'phase' => 4])->name('wiki.index');
