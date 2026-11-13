<?php

use App\Support\Currency;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;

if (! function_exists('setting')) {
    /** Read a site setting, e.g. setting('contact.email'). */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('media_url')) {
    /**
     * Public URL for a stored image path.
     * Seeded demo artwork lives in public/placeholders; uploads live on the public disk.
     */
    function media_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'placeholders/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }
}

if (! function_exists('currency')) {
    /** The visitor's selected display currency. */
    function currency(): Currency
    {
        return app(Currency::class);
    }
}
