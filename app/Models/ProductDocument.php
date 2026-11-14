<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDocument extends Model
{
    public const TYPES = [
        'datasheet' => 'Datasheet',
        'schematic' => 'Schematic',
        'pinout' => 'Pinout',
        'model_3d' => '3D model',
        'firmware' => 'Firmware',
        'other' => 'Other',
    ];

    protected $fillable = ['product_id', 'media_id', 'title', 'type', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES['other'];
    }
}
