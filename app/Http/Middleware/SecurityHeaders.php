<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response.
 *
 * Content-Security-Policy notes:
 *  - Everything is loaded from this site only: no CDNs, no third-party
 *    scripts, fonts or styles. The one exception is the map on the Contact
 *    page, which may be framed from Google Maps or OpenStreetMap.
 *  - 'unsafe-inline' and 'unsafe-eval' are needed for scripts because
 *    Alpine.js and Livewire evaluate expressions written in HTML attributes.
 *    The policy still stops pages from loading or sending data to other
 *    sites, being framed by other sites, and submitting forms elsewhere.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // While `npm run dev` is running, assets come from the Vite dev server on another port.
        if (! is_file(public_path('hot'))) {
            $headers['Content-Security-Policy'] = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "font-src 'self'",
                "connect-src 'self'",
                "frame-src 'self' https://www.google.com https://www.openstreetmap.org",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]);
        }

        // Tell browsers to use HTTPS only, once the site is actually being served over HTTPS.
        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // Do not overwrite a stricter value already set for this response (e.g. by Content Studio).
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
