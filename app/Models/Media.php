<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** An uploaded image or document in the media library (stored on the public disk). */
class Media extends Model
{
    public const KIND_IMAGE = 'image';

    public const KIND_DOCUMENT = 'document';

    /** Widths (px) of the WebP versions generated for each uploaded image. */
    public const VARIANT_WIDTHS = [400, 800, 1600];

    protected $fillable = ['kind', 'path', 'original_name', 'mime_type', 'size', 'width', 'height', 'variants', 'alt', 'uploaded_by'];

    protected function casts(): array
    {
        return ['variants' => 'array'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return $this->kind === self::KIND_IMAGE;
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** URL of the smallest generated version at least $width wide (the original until versions exist). */
    public function variantUrl(int $width): string
    {
        foreach ($this->variants ?? [] as $variantWidth => $path) {
            if ((int) $variantWidth >= $width) {
                return Storage::disk('public')->url($path);
            }
        }

        return $this->url;
    }

    /** srcset attribute value listing every generated width, or null when none exist yet. */
    public function srcset(): ?string
    {
        if (empty($this->variants)) {
            return null;
        }

        return collect($this->variants)
            ->map(fn (string $path, $width) => Storage::disk('public')->url($path)." {$width}w")
            ->implode(', ');
    }

    public function humanSize(): string
    {
        return $this->size >= 1_048_576
            ? number_format($this->size / 1_048_576, 1).' MB'
            : number_format(max(1, $this->size / 1024)).' KB';
    }
}
