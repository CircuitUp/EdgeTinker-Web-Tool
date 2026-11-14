<?php

namespace App\Livewire\Studio\Revisions;

use App\Enums\RevisionStatus;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Revision;
use App\Studio\ContentTypes\ContentTypeRegistry;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Every approved version of one item, newest first. The Content Admin can restore any of them. */
class History extends Component
{
    use StudioScreen;

    #[Locked]
    public string $type;

    #[Locked]
    public int $entityId;

    public function mount(ContentTypeRegistry $types, string $type, int $id): void
    {
        abort_unless($types->has($type), 404);

        $this->type = $type;
        $this->entityId = $id;
    }

    public function render(ContentTypeRegistry $types)
    {
        $type = $types->get($this->type);
        $current = $type->current($this->entityId);

        $versions = Revision::query()->forEntity($this->type, $this->entityId)
            ->where('status', RevisionStatus::Approved->value)
            ->with(['author:id,name', 'reviewer:id,name'])->latest('reviewed_at')->latest('id')->get();

        abort_if($current === null && $versions->isEmpty(), 404);

        return view('livewire.studio.revisions.history', [
            'typeLabel' => $type->label(),
            'name' => $current ? $type->title($current) : $versions->first()->title,
            'isLive' => $current !== null,
            'liveUrl' => $current ? $type->publicUrl($this->entityId) : null,
            'versions' => $versions,
            'pending' => Revision::query()->forEntity($this->type, $this->entityId)->pending()->with('author:id,name')->latest()->get(),
            'isAdmin' => $this->user()->isAdmin(),
            'userId' => $this->user()->id,
        ])->title('History');
    }
}
