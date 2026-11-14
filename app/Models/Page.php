<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An editable page of the public site. See App\Support\PageContent for the field definitions. */
class Page extends Model
{
    public const SLUGS = ['home', 'services', 'contact'];

    protected $fillable = ['slug', 'content', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
