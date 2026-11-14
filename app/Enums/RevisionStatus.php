<?php

namespace App\Enums;

enum RevisionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Awaiting review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    /** Still open: can be edited by its author and may yet go live. */
    public function isPending(): bool
    {
        return $this !== self::Approved;
    }
}
