<?php

namespace App\Http\Controllers\Studio\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Studio\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Failed attempts allowed before the email + IP pair is locked out. */
    public const MAX_ATTEMPTS = 5;

    /** Lockout length in seconds. */
    public const LOCKOUT_SECONDS = 15 * 60;

    public function create(): View
    {
        return view('studio.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = self::throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                'email' => "Too many failed sign-in attempts. Please try again in $minutes minute(s).",
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        // Do the same amount of hashing work whether or not the email exists,
        // so response time does not reveal which addresses have accounts.
        if ($user) {
            $passwordOk = Hash::check($credentials['password'], $user->password);
        } else {
            Hash::make($credentials['password']);
            $passwordOk = false;
        }

        if (! $user || ! $passwordOk) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                Audit::log('auth.lockout', "Sign-in locked for {$credentials['email']} after repeated failures", $user, actor: $user);
            }

            throw ValidationException::withMessages(['email' => 'These details do not match our records.']);
        }

        // The password is correct, so it is safe to explain why this account cannot sign in.
        if (! $user->hasVerifiedEmail()) {
            $request->session()->put('studio.unverified_id', $user->id);

            return redirect()->route('studio.verification.notice');
        }
        if ($user->status === UserStatus::Pending) {
            throw ValidationException::withMessages(['email' => 'Your account is waiting for the Content Admin to activate it. You will get an email when it is ready.']);
        }
        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages(['email' => 'This account has been suspended. Please contact the Content Admin.']);
        }

        RateLimiter::clear($key);

        if ($user->hasTwoFactorEnabled()) {
            // Not signed in yet: remember who passed the password step, briefly.
            $request->session()->put('studio.2fa', ['id' => $user->id, 'expires' => now()->addMinutes(10)->timestamp]);

            return redirect()->route('studio.two-factor.challenge');
        }

        return self::completeLogin($request, $user);
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            Audit::log('auth.logout', 'Signed out', $user, actor: $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('studio.login')->with('status', 'You have been signed out.');
    }

    public static function completeLogin(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate(); // prevent session fixation
        $request->session()->forget(['studio.2fa', 'studio.unverified_id']);

        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('auth.login', 'Signed in', $user, actor: $user);

        return redirect()->intended(route('studio.dashboard'));
    }

    public static function throttleKey(Request $request): string
    {
        return 'studio-login|'.Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();
    }
}
