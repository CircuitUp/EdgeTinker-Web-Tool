<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message sent through the public contact form. Kept in Content Studio as well as emailed. */
class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'company', 'subject', 'message', 'ip_address', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
