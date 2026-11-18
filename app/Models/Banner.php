<?php

namespace App\Models;

use App\Support\Banner\BannerConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * A banner design (BannerConfig JSON). There is one today, key "home": the
 * home page hero slideshow. Only approved content is stored here; pending
 * edits live in revisions until the Content Admin approves them.
 */
class Banner extends Model
{
    public const HOME = 'home';

    protected $fillable = ['key', 'config'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public static function home(): self
    {
        return self::firstOrCreate(['key' => self::HOME], ['config' => BannerConfig::empty()]);
    }

    /** The config, repaired if needed (an unreadable one falls back to an empty banner). */
    public function design(): array
    {
        return BannerConfig::fromStored($this->config);
    }

    /** Whether any banner uses this file as a slide background. */
    public static function usesMedia(Media $media): bool
    {
        foreach (self::all() as $banner) {
            foreach ($banner->design()['slides'] as $slide) {
                $bg = $slide['background'];
                if ($bg['image']['media_id'] === $media->id || $bg['video']['media_id'] === $media->id || $bg['image']['path'] === $media->path) {
                    return true;
                }
            }
        }

        return false;
    }
}
