<?php

namespace App\Livewire\Studio\Users;

use App\Enums\UserStatus;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\User;
use App\Notifications\Studio\AccountStatusChanged;
use App\Studio\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Content Admin only: activate, suspend and delete Content Creators, and send password-reset emails. */
#[Title('Users')]
class Index extends Component
{
    use StudioScreen;

    /** Every action re-checks this; the route middleware alone is not relied upon. */
    private function creator(int $userId): User
    {
        abort_unless($this->user()->isAdmin(), 403);

        // The admin account itself cannot be managed from here.
        return User::creators()->findOrFail($userId);
    }

    public function activate(int $userId): void
    {
        $creator = $this->creator($userId);

        if (! $creator->hasVerifiedEmail()) {
            $this->toast('error', "{$creator->name} has not confirmed their email address yet, so the account cannot be activated.");

            return;
        }

        $creator->status = UserStatus::Active;
        $creator->save();
        $creator->notify(new AccountStatusChanged(activated: true));

        Audit::log('user.activated', "Activated {$creator->name}", $creator);
        $this->toast('success', "{$creator->name} can now sign in. They have been emailed.");
    }

    public function suspend(int $userId): void
    {
        $creator = $this->creator($userId);

        $creator->status = UserStatus::Suspended;
        $creator->save();

        // Sign them out everywhere straight away.
        DB::table('sessions')->where('user_id', $creator->id)->delete();
        $creator->notify(new AccountStatusChanged(activated: false));

        Audit::log('user.suspended', "Suspended {$creator->name}", $creator);
        $this->toast('success', "{$creator->name} has been suspended and signed out.");
    }

    public function sendPasswordReset(int $userId): void
    {
        $creator = $this->creator($userId);
        $status = Password::sendResetLink(['email' => $creator->email]);

        if ($status === Password::RESET_LINK_SENT) {
            Audit::log('user.password_reset_sent', "Sent a password-reset email to {$creator->name}", $creator);
            $this->toast('success', "A password-reset email was sent to {$creator->email}.");
        } else {
            $this->toast('warning', 'A reset email was sent to this person a moment ago. Please wait a minute before sending another.');
        }
    }

    public function delete(int $userId): void
    {
        $creator = $this->creator($userId);
        $name = $creator->name;

        DB::table('sessions')->where('user_id', $creator->id)->delete();
        Audit::log('user.deleted', "Deleted the account of $name ({$creator->email})", $creator);

        // Their revisions and uploads are kept; the author simply shows as a deleted user.
        $creator->delete();

        $this->toast('success', "The account of $name has been deleted. Their past submissions are kept in the history.");
    }

    public function render()
    {
        abort_unless($this->user()->isAdmin(), 403);

        return view('livewire.studio.users.index', [
            'admin' => $this->user(),
            // Waiting first, then active, then suspended.
            'creators' => User::creators()->withCount('revisions')
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 ELSE 2 END")->orderBy('name')->get(),
        ]);
    }
}
