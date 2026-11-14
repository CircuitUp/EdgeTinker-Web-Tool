<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WikiPage extends Model
{
    /** The standard set of pages offered for every product. Creators can add more. */
    public const STANDARD_PAGES = [
        'Overview', 'Quick Start', 'Datasheet', 'Pinout', 'Schematics', 'Firmware & Software', 'Downloads',
    ];

    protected $fillable = ['product_id', 'title', 'slug', 'body', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Public address: /wiki/{category}/{product}/{page}. Needs product.category loaded. */
    public function url(): string
    {
        return route('wiki.show', [$this->product->category->slug, $this->product->slug, $this->slug]);
    }
}
