<?php

use App\Http\Middleware\EnsureStudioAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StudioHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Content Studio lives under /contentcreator with its own headers.
            Route::middleware(['web', 'studio.headers'])
                ->prefix('contentcreator')
                ->name('studio.')
                ->group(__DIR__.'/../routes/studio.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'studio' => EnsureStudioAccess::class,
            'studio.headers' => StudioHeaders::class,
        ]);

        // Global (not just the "web" group) so error responses such as 404 carry the headers too.
        $middleware->append(SecurityHeaders::class);

        // Behind a proxy or CDN (set TRUSTED_PROXIES in .env), trust its headers so that
        // HTTPS detection and visitor IP addresses (used for rate limiting) are correct.
        // Left unset, forwarded headers are ignored and cannot be spoofed by visitors.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // The only sign-in page is Content Studio's.
        $middleware->redirectGuestsTo(fn () => route('studio.login'));
        $middleware->redirectUsersTo(fn () => route('studio.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
