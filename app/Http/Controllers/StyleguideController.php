<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Design system reference page. The route is only registered outside production. */
class StyleguideController extends Controller
{
    public function __invoke(Request $request): View
    {
        // A fake 87-item result set so the pagination component has pages to show.
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            items: range(1, 12),
            total: 87,
            perPage: 12,
            currentPage: $page,
            options: ['path' => $request->url(), 'fragment' => 'pagination'],
        );

        return view('styleguide', [
            'products' => Product::forCard()->orderBy('id')->limit(4)->get(),
            'paginator' => $paginator,
        ]);
    }
}
