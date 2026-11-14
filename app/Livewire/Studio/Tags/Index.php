<?php

namespace App\Livewire\Studio\Tags;

use App\Enums\RevisionAction;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Revision;
use App\Models\Tag;
use App\Studio\RevisionService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Tags are small enough to manage on one screen: add, rename and delete in place. */
#[Title('Tags')]
class Index extends Component
{
    use StudioScreen;

    public string $newName = '';

    public ?int $editingId = null;

    public string $editingName = '';

    public function add(RevisionService $revisions): void
    {
        $this->resetErrorBag();

        try {
            $revision = $revisions->save($this->user(), 'tag', null, ['name' => $this->newName], submit: true);
        } catch (ValidationException $e) {
            $this->addError('newName', collect($e->errors())->flatten()->first());

            return;
        }

        $this->newName = '';
        $this->toast('success', $this->user()->isAdmin() ? "Tag “{$revision->title}” created." : "Tag “{$revision->title}” submitted for review.");
    }

    public function startEditing(int $tagId): void
    {
        $this->editingId = $tagId;
        $this->editingName = Tag::findOrFail($tagId)->name;
        $this->resetErrorBag();
    }

    public function rename(RevisionService $revisions): void
    {
        $this->resetErrorBag();

        try {
            // The filter address (slug) follows the new name.
            $revisions->save($this->user(), 'tag', $this->editingId, ['name' => $this->editingName], submit: true);
        } catch (ValidationException $e) {
            $this->addError('editingName', collect($e->errors())->flatten()->first());

            return;
        }

        $this->editingId = null;
        $this->toast('success', $this->user()->isAdmin() ? 'Tag renamed.' : 'Rename submitted for review.');
    }

    public function requestDeletion(int $tagId, RevisionService $revisions): void
    {
        if ($this->attempt(fn () => $revisions->requestDeletion($this->user(), 'tag', $tagId))) {
            $this->toast('success', $this->user()->isAdmin() ? 'Tag deleted.' : 'Deletion requested.');
        }
    }

    public function render()
    {
        $pending = Revision::query()->where('entity_type', 'tag')->pending()->with('author:id,name')->latest()->get();

        return view('livewire.studio.tags.index', [
            'tags' => Tag::withCount('products')->orderBy('name')->get(),
            'pending' => $pending->whereNotNull('entity_id')->groupBy('entity_id'),
            'newTags' => $pending->where('action', RevisionAction::Create),
            'isAdmin' => $this->user()->isAdmin(),
        ]);
    }
}
