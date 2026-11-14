<?php

namespace App\Studio;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Records who did what and when in Content Studio. */
class Audit
{
    public static function log(string $event, string $description, ?Model $subject = null, array $meta = [], ?User $actor = null): AuditLog
    {
        $actor ??= auth()->user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name,
            'event' => $event,
            'description' => mb_substr($description, 0, 500),
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
