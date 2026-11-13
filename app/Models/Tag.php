<?php

namespace App\Models;

use App\Search\MySqlProductSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(MySqlProductSearch::VOCABULARY_CACHE_KEY));
        static::deleted(fn () => Cache::forget(MySqlProductSearch::VOCABULARY_CACHE_KEY));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
