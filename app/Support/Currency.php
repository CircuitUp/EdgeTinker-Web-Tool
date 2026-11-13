<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The visitor's selected display currency (USD or LKR).
 *
 * Prices are entered by hand per currency in Content Studio; there is no
 * conversion. The choice is remembered in the `currency` cookie.
 */
class Currency
{
    public const COOKIE = 'currency';

    public const DEFAULT = 'USD';

    public const SUPPORTED = ['USD', 'LKR'];

    private ?string $code = null;

    public function __construct(private readonly Request $request) {}

    public function code(): string
    {
        if ($this->code === null) {
            $cookie = $this->request->cookie(self::COOKIE);
            $this->code = self::isSupported($cookie) ? strtoupper($cookie) : self::DEFAULT;
        }

        return $this->code;
    }

    public static function isSupported(mixed $code): bool
    {
        return is_string($code) && in_array(strtoupper($code), self::SUPPORTED, true);
    }

    /** Products column holding the price in the selected currency. */
    public function column(): string
    {
        return $this->code() === 'LKR' ? 'price_lkr' : 'price_usd';
    }

    public function format(float|string|null $amount, ?string $code = null): ?string
    {
        if ($amount === null) {
            return null;
        }

        return match ($code ?? $this->code()) {
            'LKR' => 'Rs '.number_format((float) $amount, 2),
            default => '$'.number_format((float) $amount, 2),
        };
    }
}
