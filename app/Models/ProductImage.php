<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'media_id', 'path', 'alt', 'is_thumbnail', 'sort_order'];

    protected function casts(): array
    {
        return ['is_thumbnail' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The media-library file behind this image (null for seeded placeholder artwork). */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** Full-size URL. */
    public function getUrlAttribute(): ?string
    {
        return media_url($this->path);
    }

    /** URL of a resized WebP version at least $width px wide, when one has been generated. */
    public function sized(int $width): ?string
    {
        return $this->media ? $this->media->variantUrl($width) : $this->url;
    }

    public function srcset(): ?string
    {
        return $this->media?->srcset();
    }
}
