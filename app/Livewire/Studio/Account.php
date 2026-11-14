<?php

namespace App\Livewire\Studio;

use App\Http\Controllers\Studio\Auth\RegisterController;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Studio\Audit;
use App\Studio\TwoFactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Change your password; the Content Admin can also turn two-factor authentication on or off. */
#[Title('Account')]
class Account extends Component
{
    use StudioScreen;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    // Two-factor set-up (Content Admin only)
    public bool $settingUp = false;

    public string $code = '';

    public string $confirmPassword = '';

    /** Recovery codes are shown once, straight after they are generated. */
    public array $shownRecoveryCodes = [];

    public function changePassword(): void
    {
        $user = $this->user();

        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'same:newPasswordConfirmation', RegisterController::passwordRule()],
        ], ['newPassword.same' => 'The two new passwords do not match.'], ['currentPassword' => 'current password', 'newPassword' => 'new password']);

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', 'That is not your current password.');

            return;
        }

        $user->forceFill(['password' => $this->newPassword])->save();

        // Sign out any other browsers that were signed in with the old password.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();

        Audit::log('auth.password_changed', 'Changed their password', $user);
        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        $this->toast('success', 'Your password has been changed.');
    }

    // ── Two-factor authentication ────────────────────────────────────────────

    public function startTwoFactor(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        abort_unless($user->isAdmin(), 403);

        // Stored unconfirmed: it only takes effect once a code from the app has been checked.
        $user->forceFill(['two_factor_secret' => $twoFactor->generateSecret(), 'two_factor_confirmed_at' => null])->save();
        $this->settingUp = true;
        $this->code = '';
        $this->resetErrorBag();
    }

    public function confirmTwoFactor(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        abort_unless($user->isAdmin() && $user->two_factor_secret, 403);
        $this->resetErrorBag(); // clear any "wrong code" message from an earlier attempt

        if (! $twoFactor->verifyCode($user, $user->two_factor_secret, $this->code)) {
            $this->addError('code', 'That code is not valid. Check that your phone’s clock is correct and try the next code.');

            return;
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();

        Audit::log('auth.two_factor_enabled', 'Turned on two-factor authentication', $user);
        $this->settingUp = false;
        $this->code = '';
        $this->shownRecoveryCodes = $codes;
        $this->toast('success', 'Two-factor authentication is on.');
    }

    public function cancelTwoFactor(TwoFactor $twoFactor): void
    {
        $user = $this->user();

        if (! $user->hasTwoFactorEnabled()) {
            $twoFactor->disable($user);
        }
        $this->settingUp = false;
    }

    public function regenerateRecoveryCodes(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        abort_unless($user->isAdmin() && $user->hasTwoFactorEnabled(), 403);

        if (! $this->passwordConfirmed()) {
            return;
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        Audit::log('auth.recovery_codes_regenerated', 'Generated new two-factor recovery codes', $user);
        $this->shownRecoveryCodes = $codes;
        $this->toast('success', 'New recovery codes generated. The old ones no longer work.');
    }

    public function disableTwoFactor(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        abort_unless($user->isAdmin(), 403);

        if (! $this->passwordConfirmed()) {
            return;
        }

        $twoFactor->disable($user);
        Audit::log('auth.two_factor_disabled', 'Turned off two-factor authentication', $user);
        $this->shownRecoveryCodes = [];
        $this->toast('success', 'Two-factor authentication is off.');
    }

    private function passwordConfirmed(): bool
    {
        $this->resetErrorBag();
        $ok = Hash::check($this->confirmPassword, $this->user()->password);
        $this->confirmPassword = '';

        if (! $ok) {
            $this->addError('confirmPassword', 'Enter your current password to confirm this change.');
        }

        return $ok;
    }

    public function render(TwoFactor $twoFactor)
    {
        $user = $this->user();

        return view('livewire.studio.account', [
            'user' => $user,
            'twoFactorOn' => $user->hasTwoFactorEnabled(),
            'qrCode' => $this->settingUp && $user->two_factor_secret ? $twoFactor->qrCodeSvg($user, $user->two_factor_secret) : null,
            'secret' => $this->settingUp ? $user->two_factor_secret : null,
            'recoveryCodesLeft' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }
}
