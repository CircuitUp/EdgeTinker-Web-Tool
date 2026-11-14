<?php

namespace App\Http\Controllers\Studio\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Studio\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('studio.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::sendResetLink($request->only('email'));

        // Same answer whether or not the address has an account, so it cannot be used to find accounts.
        return back()->with('status', 'If that address has an account, a password reset link is on its way.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('studio.auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', RegisterController::passwordRule()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
                Audit::log('auth.password_reset', 'Reset their password by email', $user, actor: $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This reset link is invalid or has expired. Please request a new one.']);
        }

        return redirect()->route('studio.login')->with('status', 'Your password has been changed. You can sign in now.');
    }
}
