<?php

namespace App\Http\Controllers\Studio\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Studio\Audit;
use App\Studio\TwoFactor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Second sign-in step for accounts with two-factor authentication enabled. */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) ? view('studio.auth.two-factor') : redirect()->route('studio.login');
    }

    public function store(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('studio.login')->with('status', 'Your sign-in timed out. Please start again.');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:40'],
        ]);

        $key = "studio-2fa|{$user->id}|".$request->ip();
        if (RateLimiter::tooManyAttempts($key, LoginController::MAX_ATTEMPTS)) {
            $request->session()->forget('studio.2fa');
            Audit::log('auth.lockout', 'Two-factor step locked after repeated wrong codes', $user, actor: $user);

            return redirect()->route('studio.login')->withErrors(['email' => 'Too many wrong codes. Please wait 15 minutes and sign in again.']);
        }

        $ok = filled($data['recovery_code'] ?? null)
            ? $twoFactor->consumeRecoveryCode($user, $data['recovery_code'])
            : $twoFactor->verifyCode($user, $user->two_factor_secret, (string) ($data['code'] ?? ''));

        if (! $ok) {
            RateLimiter::hit($key, LoginController::LOCKOUT_SECONDS);

            throw ValidationException::withMessages(['code' => 'That code is not valid. Check your authenticator app and try again.']);
        }

        RateLimiter::clear($key);

        if (filled($data['recovery_code'] ?? null)) {
            Audit::log('auth.recovery_code', 'Signed in with a two-factor recovery code', $user, actor: $user);
        }

        return LoginController::completeLogin($request, $user);
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('studio.2fa');

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget('studio.2fa');

            return null;
        }

        $user = User::find($pending['id']);

        return $user && $user->isActive() && $user->hasTwoFactorEnabled() ? $user : null;
    }
}
