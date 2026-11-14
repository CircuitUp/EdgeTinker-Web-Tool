<?php

namespace App\Enums;

enum RevisionAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'New',
            self::Update => 'Edit',
            self::Delete => 'Delete',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Create => 'success',
            self::Update => 'info',
            self::Delete => 'danger',
        };
    }
}
