<?php

namespace App\Http\Controllers;

use App\Models\BannerSlide;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'slides' => BannerSlide::active()->get(),
            'categories' => Category::navigation(),
            'featured' => Product::featured()
                ->forCard()
                ->latest()
                ->limit(config('edgetinker.home.featured_limit'))
                ->get(),
        ]);
    }
}
