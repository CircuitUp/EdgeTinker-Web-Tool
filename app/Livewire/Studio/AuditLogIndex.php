<?php

namespace App\Livewire\Studio;

use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\AuditLog;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Content Admin only: who did what and when. Read-only. */
#[Title('Audit log')]
class AuditLogIndex extends Component
{
    use StudioScreen, WithPagination;

    public const AREAS = [
        'revision' => 'Content changes',
        'user' => 'Users',
        'auth' => 'Signing in',
        'media' => 'Media library',
        'settings' => 'Site settings',
        'message' => 'Messages',
    ];

    #[Url]
    public string $area = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updating($name): void
    {
        if (in_array($name, ['area', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        abort_unless($this->user()->isAdmin(), 403);

        $entries = AuditLog::query()
            ->when(isset(self::AREAS[$this->area]), fn ($query) => $query->where('event', 'like', $this->area.'.%'))
            ->when($this->search !== '', function ($query) {
                $like = '%'.addcslashes($this->search, '\\%_').'%';
                $query->where(fn ($q) => $q->where('description', 'like', $like)->orWhere('user_name', 'like', $like));
            })
            ->latest('id')
            ->paginate(40);

        return view('livewire.studio.audit-log', ['entries' => $entries, 'areas' => self::AREAS]);
    }
}
