<?php

namespace App\Livewire\Studio;

use App\Enums\RevisionStatus;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Revision;
use App\Models\User;
use App\Studio\ContentTypes\ContentTypeRegistry;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    use StudioScreen;

    public function render(ContentTypeRegistry $types)
    {
        $user = $this->user();

        $mine = Revision::query()->where('author_id', $user->id)->pending()->latest('updated_at')->limit(12)->get();

        return view('livewire.studio.dashboard', [
            'user' => $user,
            'typeLabels' => $types->labels(),
            'myDrafts' => $mine->where('status', RevisionStatus::Draft),
            'myWaiting' => $mine->where('status', RevisionStatus::Submitted),
            'myRejected' => $mine->where('status', RevisionStatus::Rejected),
            'myApproved' => Revision::query()->where('author_id', $user->id)
                ->where('status', RevisionStatus::Approved->value)->latest('reviewed_at')->limit(5)->get(),

            // Content Admin only
            'queue' => $user->isAdmin() ? Revision::submitted()->with('author')->oldest('submitted_at')->limit(8)->get() : collect(),
            'queueTotal' => $user->isAdmin() ? Revision::submitted()->count() : 0,
            'pendingUsers' => $user->isAdmin()
                ? User::creators()->where('status', 'pending')->whereNotNull('email_verified_at')->count() : 0,
            'activity' => $user->isAdmin() ? AuditLog::latest('id')->limit(10)->get() : collect(),
            'liveProducts' => Product::count(),
        ]);
    }
}
