<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Site settings (logo, contact details, social links) editable in Content Studio.
 * Stored values override the defaults in config/edgetinker.php.
 */
class Settings
{
    public const CACHE_KEY = 'settings.all';

    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        if ($value === null || $value === '') {
            // Setting keys contain dots ("contact.email"), so index the array
            // directly instead of using config()'s dot notation.
            return config('edgetinker.settings')[$key] ?? $default;
        }

        return $value;
    }

    /** True while a setting still holds its shipped placeholder value. */
    public function isPlaceholder(string $key): bool
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '';
    }

    public function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    private function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            return $this->loaded = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => Setting::query()->pluck('value', 'key')->all(),
            );
        } catch (Throwable) {
            // Database not migrated yet (fresh install): fall back to defaults.
            return $this->loaded = [];
        }
    }
}
