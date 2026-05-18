<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Platform-wide singleton: platform name + logo. Lives in its own
 * collection rather than sitting on a Company row because it isn't
 * tenant-scoped — there is exactly one document, owned by the platform.
 *
 * Use `PlatformSetting::singleton()` to fetch/lazily create it.
 */
class PlatformSetting extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'platform_settings';

    protected $fillable = [
        'platform_name',  // shown in browser tab title, defaults to "Nautiqs"
        'logo_path',      // public/-relative path under storage/app/public
    ];

    public static function singleton(): self
    {
        $row = static::first();
        if ($row) return $row;
        return static::create([
            'platform_name' => 'Nautiqs',
        ]);
    }
}
