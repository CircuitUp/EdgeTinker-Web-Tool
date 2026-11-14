<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';       // registered, waiting for the admin to activate
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting activation',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
        };
    }
}
