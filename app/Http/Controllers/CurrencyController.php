<?php

namespace App\Http\Controllers;

use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', Rule::in(Currency::SUPPORTED)],
        ]);

        // Remember the choice for a year, then return to the page the visitor was on.
        return redirect()->to($this->returnUrl())
            ->withCookie(cookie(Currency::COOKIE, $data['currency'], 60 * 24 * 365));
    }

    /**
     * The previous page, minus any price-range filter: a range chosen in one
     * currency means nothing in the other (and minus the page number, since
     * the result set changes with it).
     */
    private function returnUrl(): string
    {
        $previous = url()->previous();
        $parts = parse_url($previous);

        // The "previous page" comes from the Referer header; never redirect off-site.
        if (($parts['host'] ?? null) !== request()->getHost()) {
            return route('home');
        }

        if (empty($parts['query'])) {
            return $previous;
        }

        parse_str($parts['query'], $query);
        $query = Arr::except($query, ['min', 'max', 'page']);

        return strtok($previous, '?').($query ? '?'.Arr::query($query) : '');
    }
}
