<?php

namespace App\Http\Controllers\Studio\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Studio\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Content Creator self-registration. A new account is a creator, unverified
 * and pending: it cannot sign in until the email is confirmed AND the
 * Content Admin activates it. The admin account is never created here.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('studio.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: real people leave this hidden field empty.
        if (filled($request->input('website'))) {
            return redirect()->route('studio.verification.notice');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', self::passwordRule()],
        ], [
            'email.unique' => 'An account with this email address already exists. Try signing in or resetting your password.',
        ]);

        // role and status are not mass assignable; the model defaults (creator, pending) apply.
        $user = User::create($data);
        $user->sendEmailVerificationNotification();

        Audit::log('user.registered', "{$user->name} registered as a Content Creator", $user, actor: $user);

        $request->session()->put('studio.unverified_id', $user->id);

        return redirect()->route('studio.verification.notice');
    }

    /** One password policy for registration, reset and change. */
    public static function passwordRule(): Password
    {
        return Password::min(10)->letters()->mixedCase()->numbers();
    }
}
