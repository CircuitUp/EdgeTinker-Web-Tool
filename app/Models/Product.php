<?php

namespace App\Models;

use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'short_description', 'description',
        'key_features', 'price_usd', 'price_lkr', 'stock_status', 'is_featured',
        'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'key_features' => 'array',
            'price_usd' => 'decimal:2',
            'price_lkr' => 'decimal:2',
            'stock_status' => StockStatus::class,
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** The image chosen as the card thumbnail (falls back to the first image). */
    public function thumbnail(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->orderByDesc('is_thumbnail')
            ->orderBy('sort_order');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /** Eager-loads what a product card needs. */
    public function scopeForCard(Builder $query): Builder
    {
        return $query->with(['thumbnail', 'category:id,name,slug']);
    }

    /** Price in the given currency code, or null when not set ("price on request"). */
    public function priceIn(string $currencyCode): ?string
    {
        return $currencyCode === 'LKR' ? $this->price_lkr : $this->price_usd;
    }
}
