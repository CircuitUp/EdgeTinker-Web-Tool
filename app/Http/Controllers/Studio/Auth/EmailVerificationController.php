<?php

namespace App\Http\Controllers\Studio\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Studio\CreatorAwaitingActivation;
use App\Studio\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /** "Check your inbox" page shown after registering. */
    public function notice(): View
    {
        return view('studio.auth.verify-email');
    }

    /** The signed link from the email. Works without being signed in. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'This verification link is not valid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            Audit::log('user.verified', "{$user->name} confirmed their email address", $user, actor: $user);

            if ($user->status === UserStatus::Pending) {
                User::admin()?->notify(new CreatorAwaitingActivation($user));
            }
        }

        $message = $user->isActive()
            ? 'Your email address is confirmed. You can sign in now.'
            : 'Your email address is confirmed. The Content Admin will now review your account; you will get an email when you can sign in.';

        return redirect()->route('studio.login')->with('status', $message);
    }

    /** Send the link again (to the account remembered from registration or a sign-in attempt). */
    public function resend(Request $request): RedirectResponse
    {
        $user = User::find($request->session()->get('studio.unverified_id'));

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'If your address still needs confirming, a new link is on its way.');
    }
}
