<?php

namespace App\Enums;

/** Exactly two roles. There is only ever one Content Admin. */
enum UserRole: string
{
    case Admin = 'admin';
    case Creator = 'creator';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Content Admin',
            self::Creator => 'Content Creator',
        };
    }
}
