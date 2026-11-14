<?php

namespace App\Livewire\Studio\Revisions;

use App\Enums\RevisionStatus;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Revision;
use App\Studio\ContentTypes\ContentTypeRegistry;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Two screens share this list:
 *  - "Review queue" (/review, Content Admin only): everyone's changes, submitted ones by default.
 *  - "My submissions" (/submissions): the signed-in person's own changes.
 */
class Index extends Component
{
    use StudioScreen, WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    public bool $reviewing = false;

    public function mount(): void
    {
        $this->reviewing = request()->routeIs('studio.review.*');

        // The route middleware already restricts /review to the admin; never rely on that alone.
        abort_if($this->reviewing && ! $this->user()->isAdmin(), 403);

        if ($this->status === '') {
            $this->status = $this->reviewing ? RevisionStatus::Submitted->value : 'open';
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['status', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function render(ContentTypeRegistry $types)
    {
        abort_if($this->reviewing && ! $this->user()->isAdmin(), 403);

        $revisions = Revision::query()
            ->with(['author:id,name', 'reviewer:id,name'])
            ->when(! $this->reviewing, fn ($query) => $query->where('author_id', $this->user()->id))
            ->when($this->status === 'open', fn ($query) => $query->pending())
            ->when(RevisionStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status->value))
            ->when($types->has($this->type), fn ($query) => $query->where('entity_type', $this->type))
            // The queue is worked oldest-first; personal lists show the latest activity first.
            ->when($this->reviewing && $this->status === RevisionStatus::Submitted->value,
                fn ($query) => $query->oldest('submitted_at'),
                fn ($query) => $query->latest('updated_at'))
            ->paginate(20);

        return view('livewire.studio.revisions.index', [
            'revisions' => $revisions,
            'typeLabels' => $types->labels(),
            'statuses' => RevisionStatus::cases(),
        ])->title($this->reviewing ? 'Review queue' : 'My submissions');
    }
}
