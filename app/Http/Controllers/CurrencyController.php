<?php

namespace App\Http\Controllers;

use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', Rule::in(Currency::SUPPORTED)],
        ]);

        // Remember the choice for a year, then return to the page the visitor was on.
        return back()->withCookie(cookie(Currency::COOKIE, $data['currency'], 60 * 24 * 365));
    }
}
