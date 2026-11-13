<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    public const NAV_CACHE_KEY = 'nav.categories';

    protected $fillable = ['name', 'slug', 'tagline', 'description', 'image_path', 'sort_order'];

    protected static function booted(): void
    {
        // The header mega menu and footer read a cached list; keep it fresh.
        static::saved(fn () => Cache::forget(self::NAV_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::NAV_CACHE_KEY));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getImageUrlAttribute(): ?string
    {
        return media_url($this->image_path);
    }

    /** Categories in display order, cached for the header and footer. */
    public static function navigation(): Collection
    {
        // Cache plain arrays (not model objects) so any cache store can hold them safely.
        $rows = Cache::remember(
            self::NAV_CACHE_KEY,
            now()->addMinutes(30),
            fn () => static::query()->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (self $category) => $category->getAttributes())->all(),
        );

        return static::hydrate($rows);
    }
}
