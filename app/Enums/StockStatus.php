<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
    case ComingSoon = 'coming_soon';

    public function label(): string
    {
        return __('site.stock.'.$this->value);
    }

    /** Badge colour variant (see components/badge.blade.php). */
    public function variant(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::OutOfStock => 'danger',
            self::ComingSoon => 'info',
        };
    }

    /** Counts as available for the "in stock only" filter. */
    public function isAvailable(): bool
    {
        return $this === self::InStock || $this === self::LowStock;
    }

    /** schema.org availability URL for Product structured data. */
    public function schemaOrg(): string
    {
        return 'https://schema.org/'.match ($this) {
            self::InStock => 'InStock',
            self::LowStock => 'LimitedAvailability',
            self::OutOfStock => 'OutOfStock',
            self::ComingSoon => 'PreOrder',
        };
    }
}
