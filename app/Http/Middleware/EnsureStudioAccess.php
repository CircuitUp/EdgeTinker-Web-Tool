<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only signed-in, email-verified, ACTIVE accounts into Content Studio.
 * Checked on every request, so suspending someone locks them out immediately
 * even if they are signed in. With `:admin` it also requires the Content Admin.
 */
class EnsureStudioAccess
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('studio.login'));
        }

        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('studio.login')->with('status', 'Your account is not active. Please contact the Content Admin.');
        }

        if ($role === 'admin' && ! $user->isAdmin()) {
            abort(403, 'Only the Content Admin can open this page.');
        }

        return $next($request);
    }
}
