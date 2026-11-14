<?php

namespace App\Livewire\Studio\Revisions;

use App\Enums\RevisionAction;
use App\Enums\RevisionStatus;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Revision;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\RevisionService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One change in detail: who made it, a before/after comparison, a preview of
 * the page as it would look live, and the actions the viewer is allowed to take.
 */
class Show extends Component
{
    use StudioScreen;

    #[Locked]
    public int $revisionId;

    /** Opened from the review queue (rather than "My submissions"); decides where Back goes. */
    #[Locked]
    public bool $fromReview = false;

    public string $comment = '';

    public bool $onlyChanges = true;

    public bool $rejecting = false;

    public function mount(int $revision): void
    {
        $record = Revision::findOrFail($revision);
        $this->authorizeView($record);
        $this->revisionId = $record->id;
        $this->fromReview = request()->routeIs('studio.review.*');
    }

    private function revision(): Revision
    {
        $revision = Revision::with(['author', 'reviewer'])->findOrFail($this->revisionId);
        $this->authorizeView($revision);

        return $revision;
    }

    /** The Content Admin sees every change; creators only their own. */
    private function authorizeView(Revision $revision): void
    {
        abort_unless($this->user()->isAdmin() || $revision->author_id === $this->user()->id, 403);
    }

    // ── Content Admin ────────────────────────────────────────────────────────

    public function approve(RevisionService $revisions)
    {
        $revision = $this->revision();

        if (! $this->attempt(fn () => $revisions->approve($revision, $this->user(), trim($this->comment) ?: null))) {
            return null;
        }

        $this->flashToast('success', $revision->action === RevisionAction::Delete
            ? "“{$revision->title}” has been deleted from the live site."
            : "“{$revision->title}” is now live.");

        return $this->redirectRoute('studio.review.index');
    }

    public function reject(RevisionService $revisions)
    {
        $revision = $this->revision();

        if (trim($this->comment) === '') {
            $this->addError('comment', 'Please tell the creator what needs to change.');

            return null;
        }

        if (! $this->attempt(fn () => $revisions->reject($revision, $this->user(), $this->comment))) {
            return null;
        }

        $this->flashToast('success', "“{$revision->title}” was sent back to {$revision->author?->name} with your comment.");

        return $this->redirectRoute('studio.review.index');
    }

    public function restore(RevisionService $revisions)
    {
        $revision = $this->revision();

        $restored = null;
        if (! $this->attempt(function () use ($revisions, $revision, &$restored) {
            $restored = $revisions->restore($revision, $this->user());
        })) {
            return null;
        }

        $this->flashToast('success', "“{$revision->title}” was restored to this version and is live.");

        return $this->redirectRoute('studio.history', ['type' => $restored->entity_type, 'id' => $restored->entity_id]);
    }

    // ── Author ───────────────────────────────────────────────────────────────

    public function submit(RevisionService $revisions)
    {
        $revision = $this->revision();

        if (! $this->attempt(fn () => $revisions->save($this->user(), $revision->entity_type, $revision->entity_id, $revision->payload ?? [], true, $revision))) {
            return null;
        }

        $this->flashToast('success', $this->user()->isAdmin() ? "“{$revision->title}” is now live." : "“{$revision->title}” was submitted for review.");

        return $this->redirectRoute('studio.revisions.index');
    }

    public function discard(RevisionService $revisions)
    {
        $revision = $this->revision();
        $title = $revision->title;

        if (! $this->attempt(fn () => $revisions->discard($revision, $this->user()))) {
            return null;
        }

        $this->flashToast('success', "Your pending change to “{$title}” was discarded. The live site is unchanged.");

        return $this->redirectRoute('studio.revisions.index');
    }

    public function render(ContentTypeRegistry $types)
    {
        $revision = $this->revision();
        $type = $types->get($revision->entity_type);
        $user = $this->user();

        // "Before" is what was live when it was approved (history) or what is live now (pending).
        $beforePayload = $revision->status === RevisionStatus::Approved
            ? $revision->previous
            : ($revision->entity_id ? $type->current($revision->entity_id) : null);

        $before = $beforePayload ? $type->summary($beforePayload) : [];
        $after = $revision->payload ? $type->summary($revision->payload) : [];

        $rows = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $label) {
            $old = $before[$label] ?? null;
            $new = $after[$label] ?? null;
            $rows[] = [
                'label' => $label,
                'before' => $old,
                'after' => $new,
                'changed' => $revision->action !== RevisionAction::Delete && $before !== [] && $old !== $new,
            ];
        }

        $isAuthor = $revision->author_id === $user->id;

        return view('livewire.studio.revisions.show', [
            'revision' => $revision,
            'typeLabel' => $type->label(),
            'rows' => $rows,
            'hasBefore' => $before !== [],
            'hasAfter' => $after !== [],
            'changedCount' => collect($rows)->where('changed', true)->count(),
            'previewUrl' => $type->hasPreview() ? route('studio.revisions.preview', $revision) : null,
            'liveUrl' => $revision->entity_id ? $type->publicUrl($revision->entity_id) : null,
            'editUrl' => $revision->isEditable() && ($isAuthor || $user->isAdmin()) ? $type->editUrl($revision->entity_id, $revision->id) : null,
            'historyUrl' => $revision->entity_id ? route('studio.history', ['type' => $revision->entity_type, 'id' => $revision->entity_id]) : null,
            'canReview' => $user->isAdmin() && $revision->status === RevisionStatus::Submitted,
            'canSubmit' => $isAuthor && $revision->isEditable() && in_array($revision->status, [RevisionStatus::Draft, RevisionStatus::Rejected], true),
            'canDiscard' => ($isAuthor || $user->isAdmin()) && $revision->status !== RevisionStatus::Approved,
            'canRestore' => $user->isAdmin() && $revision->status === RevisionStatus::Approved
                && ! empty($revision->action === RevisionAction::Delete ? $revision->previous : $revision->payload),
            'isAdmin' => $user->isAdmin(),
            'backUrl' => route($this->fromReview ? 'studio.review.index' : 'studio.revisions.index'),
        ])->title($revision->title);
    }
}
