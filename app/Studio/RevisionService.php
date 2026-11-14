<?php

namespace App\Studio;

use App\Enums\RevisionAction;
use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\User;
use App\Notifications\Studio\RevisionReviewed;
use App\Notifications\Studio\SubmissionReceived;
use App\Studio\ContentTypes\ContentType;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Support\SiteCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The approval workflow.
 *
 *   Draft → Submitted → Approved (goes live)
 *                     → Rejected (returns to the creator with a comment)
 *
 * Rules enforced here, on the server, for every content type:
 *  - The live tables are only written inside approve(). Nothing else publishes.
 *  - Only the Content Admin can approve or reject.
 *  - The admin's own changes are approved immediately but still recorded as a revision.
 *  - A new item exists only as a revision until its first approval.
 *  - Deletions are revisions too: the item stays live until the admin approves.
 *  - When one change to an item is approved, other pending changes to the same
 *    item are flagged as needing a rebase, because they were written against
 *    content that is no longer live.
 */
class RevisionService
{
    public function __construct(private readonly ContentTypeRegistry $types) {}

    /**
     * Save a create/update. Creators get a draft or a submission; the admin's
     * save (with $submit) is approved on the spot.
     *
     * @param  Revision|null  $revision  an existing pending revision being edited, if any
     */
    public function save(User $user, string $typeKey, ?int $entityId, array $payload, bool $submit, ?Revision $revision = null): Revision
    {
        $type = $this->types->get($typeKey);

        if ($revision) {
            $this->assertCanEdit($user, $revision);
            $entityId = $revision->entity_id;
        }

        $payload = $this->validated($type, $payload, $entityId);

        $attributes = [
            'entity_type' => $type->key(),
            'entity_id' => $entityId,
            'action' => $entityId ? RevisionAction::Update : RevisionAction::Create,
            'title' => $type->title($payload),
            'payload' => $payload,
            // Saving again means the author has seen the current live content.
            'needs_rebase' => false,
            'rebase_note' => null,
        ];

        if ($revision) {
            $revision->fill($attributes);
        } else {
            $revision = new Revision($attributes + ['author_id' => $user->id]);
        }

        if (! $submit) {
            $revision->status = RevisionStatus::Draft;
            $revision->save();
            Audit::log('revision.saved', "Saved a draft of {$type->label()} “{$revision->title}”", $revision, actor: $user);

            return $revision;
        }

        $revision->status = RevisionStatus::Submitted;
        $revision->submitted_at = now();
        $revision->reviewer_id = null;
        $revision->reviewed_at = null;
        $revision->comment = null;
        $revision->save();

        if ($user->isAdmin()) {
            return $this->approve($revision, $user);
        }

        Audit::log('revision.submitted', "Submitted {$type->label()} “{$revision->title}” for approval", $revision, actor: $user);
        $this->notifyAdmin($revision);

        return $revision;
    }

    /** Ask for a live item to be deleted. It stays live until approved (immediately, for the admin). */
    public function requestDeletion(User $user, string $typeKey, int $entityId): Revision
    {
        $type = $this->types->get($typeKey);
        $current = $type->current($entityId) ?? throw ValidationException::withMessages(['entity' => 'This item no longer exists.']);

        $existing = Revision::query()->forEntity($type->key(), $entityId)
            ->where('action', RevisionAction::Delete->value)->pending()->first();
        if ($existing) {
            throw ValidationException::withMessages(['entity' => 'A deletion request for this item is already waiting for review.']);
        }

        $revision = Revision::create([
            'entity_type' => $type->key(),
            'entity_id' => $entityId,
            'action' => RevisionAction::Delete,
            'status' => RevisionStatus::Submitted,
            'title' => $type->title($current),
            'payload' => null,
            'author_id' => $user->id,
            'submitted_at' => now(),
        ]);

        if ($user->isAdmin()) {
            return $this->approve($revision, $user);
        }

        Audit::log('revision.submitted', "Requested deletion of {$type->label()} “{$revision->title}”", $revision, actor: $user);
        $this->notifyAdmin($revision);

        return $revision;
    }

    /** Publish a submitted revision. This is the only place live content is written. */
    public function approve(Revision $revision, User $admin, ?string $comment = null): Revision
    {
        $this->assertAdmin($admin);

        if ($revision->status !== RevisionStatus::Submitted) {
            throw ValidationException::withMessages(['revision' => 'Only submitted changes can be approved.']);
        }
        if ($revision->needs_rebase) {
            throw ValidationException::withMessages(['revision' => 'This change was written against an older version of the item. '
                .'The creator needs to review it and submit it again.']);
        }

        $type = $this->types->get($revision->entity_type);

        DB::transaction(function () use ($revision, $type, $admin, $comment) {
            $previous = $revision->entity_id ? $type->current($revision->entity_id) : null;

            if ($revision->action === RevisionAction::Delete) {
                if ($previous !== null) {
                    $type->remove($revision->entity_id);
                }
            } else {
                // Re-validate against today's live data: a slug or SKU that was
                // free when the creator wrote this may have been taken since.
                $payload = $this->validated($type, $revision->payload ?? [], $revision->entity_id, forApproval: true);
                $revision->payload = $payload;
                $revision->entity_id = $type->apply($payload, $previous === null ? null : $revision->entity_id);
            }

            $revision->fill([
                'status' => RevisionStatus::Approved,
                'previous' => $previous,
                'reviewer_id' => $admin->id,
                'reviewed_at' => now(),
                'comment' => $comment,
            ])->save();

            $this->flagSiblings($revision, $admin);
        });

        SiteCache::flush();

        Audit::log('revision.approved', $this->describe('Approved', $revision, $type), $revision, actor: $admin);

        if ($revision->author && $revision->author_id !== $admin->id) {
            $revision->author->notify(new RevisionReviewed($revision));
        }

        return $revision;
    }

    /** Send a submission back to its author with a comment explaining why. */
    public function reject(Revision $revision, User $admin, string $comment): Revision
    {
        $this->assertAdmin($admin);

        if ($revision->status !== RevisionStatus::Submitted) {
            throw ValidationException::withMessages(['revision' => 'Only submitted changes can be rejected.']);
        }
        if (trim($comment) === '') {
            throw ValidationException::withMessages(['comment' => 'Please tell the creator what needs to change.']);
        }

        $revision->fill([
            'status' => RevisionStatus::Rejected,
            'reviewer_id' => $admin->id,
            'reviewed_at' => now(),
            'comment' => trim($comment),
        ])->save();

        Audit::log('revision.rejected', $this->describe('Rejected', $revision, $this->types->get($revision->entity_type)), $revision, actor: $admin);

        if ($revision->author && $revision->author_id !== $admin->id) {
            $revision->author->notify(new RevisionReviewed($revision));
        }

        return $revision;
    }

    /** Throw away a pending revision (its author, or the admin). Live content is not affected. */
    public function discard(Revision $revision, User $user): void
    {
        if ($revision->status === RevisionStatus::Approved) {
            throw ValidationException::withMessages(['revision' => 'Approved changes are part of the history and cannot be discarded.']);
        }
        if (! $user->isAdmin() && $revision->author_id !== $user->id) {
            throw new AuthorizationException('You can only discard your own changes.');
        }

        Audit::log('revision.discarded', "Discarded a pending change to “{$revision->title}”", $revision, actor: $user);
        $revision->delete();
    }

    /** Bring back an earlier approved version by publishing it again as a new revision. */
    public function restore(Revision $approved, User $admin): Revision
    {
        $this->assertAdmin($admin);

        // For a deletion, the content to bring back is what was live before it.
        $payload = $approved->action === RevisionAction::Delete ? $approved->previous : $approved->payload;

        if ($approved->status !== RevisionStatus::Approved || empty($payload)) {
            throw ValidationException::withMessages(['revision' => 'Only approved versions with content can be restored.']);
        }

        $type = $this->types->get($approved->entity_type);
        $exists = $approved->entity_id && $type->current($approved->entity_id) !== null;

        $revision = $this->save($admin, $type->key(), $exists ? $approved->entity_id : null, $payload, submit: true);
        Audit::log('revision.restored', "Restored {$type->label()} “{$revision->title}” to the version of {$approved->reviewed_at?->toDayDateTimeString()}", $revision, actor: $admin);

        return $revision;
    }

    /** Other open revisions of the same live item, e.g. to warn before starting a second edit. */
    public function pendingFor(string $typeKey, int $entityId)
    {
        return Revision::query()->forEntity($typeKey, $entityId)->pending()->with('author')->latest()->get();
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function validated(ContentType $type, array $payload, ?int $entityId, bool $forApproval = false): array
    {
        $payload = $type->normalise($payload, $entityId);

        $validator = Validator::make($payload, $type->rules($payload, $entityId), $type->validationMessages(), $type->validationAttributes());

        if ($validator->fails()) {
            // At approval time the admin is not looking at a form, so gather the messages into one.
            throw $forApproval
                ? ValidationException::withMessages(['revision' => 'This change can no longer be published as it is: '.implode(' ', $validator->errors()->all())])
                : new ValidationException($validator);
        }

        return $payload;
    }

    /**
     * After a change to an item goes live, every other open change to that
     * item is out of date. A deletion makes them moot, so those are rejected.
     */
    private function flagSiblings(Revision $approved, User $admin): void
    {
        if (! $approved->entity_id) {
            return;
        }

        $siblings = Revision::query()->forEntity($approved->entity_type, $approved->entity_id)
            ->pending()->whereKeyNot($approved->id)->get();

        foreach ($siblings as $sibling) {
            if ($approved->action === RevisionAction::Delete) {
                $sibling->fill([
                    'status' => RevisionStatus::Rejected,
                    'reviewer_id' => $admin->id,
                    'reviewed_at' => now(),
                    'comment' => 'This item was deleted, so the change can no longer be applied.',
                ])->save();
            } else {
                $sibling->fill([
                    'needs_rebase' => true,
                    'rebase_note' => 'Another change to this item was approved on '.now()->toDayDateTimeString()
                        .'. Open your change, check it against the current live version, then save or submit it again.',
                ])->save();
            }
        }
    }

    private function notifyAdmin(Revision $revision): void
    {
        User::admin()?->notify(new SubmissionReceived($revision));
    }

    private function describe(string $verb, Revision $revision, ContentType $type): string
    {
        $what = match ($revision->action) {
            RevisionAction::Create => 'new',
            RevisionAction::Update => 'changes to',
            RevisionAction::Delete => 'deletion of',
        };
        $by = $revision->author && $revision->author_id !== $revision->reviewer_id ? " by {$revision->author->name}" : '';

        return "$verb $what {$type->label()} “{$revision->title}”$by";
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->isAdmin() || ! $user->isActive()) {
            throw new AuthorizationException('Only the Content Admin can review changes.');
        }
    }

    private function assertCanEdit(User $user, Revision $revision): void
    {
        if (! $revision->isEditable()) {
            throw ValidationException::withMessages(['revision' => 'This change can no longer be edited.']);
        }
        if (! $user->isAdmin() && $revision->author_id !== $user->id) {
            throw new AuthorizationException('You can only edit your own changes.');
        }
    }
}
