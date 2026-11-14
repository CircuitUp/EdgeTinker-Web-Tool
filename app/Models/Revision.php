<?php

namespace App\Models;

use App\Enums\RevisionAction;
use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One proposed (or historical) change to a piece of content.
 * See App\Studio\RevisionService for the workflow rules.
 */
class Revision extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'action', 'status', 'title', 'payload', 'previous',
        'author_id', 'reviewer_id', 'comment', 'needs_rebase', 'rebase_note', 'submitted_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => RevisionAction::class,
            'status' => RevisionStatus::class,
            'payload' => 'array',
            'previous' => 'array',
            'needs_rebase' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** Not yet approved: drafts, submissions and rejected revisions awaiting rework. */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', '!=', RevisionStatus::Approved->value);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', RevisionStatus::Submitted->value);
    }

    public function scopeForEntity(Builder $query, string $type, ?int $id): Builder
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }

    public function isEditable(): bool
    {
        return $this->status !== RevisionStatus::Approved && $this->action !== RevisionAction::Delete;
    }
}
