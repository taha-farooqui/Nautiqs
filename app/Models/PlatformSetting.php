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
        // Branding
        'platform_name',          // shown in browser tab title, defaults to "Nautiqs"
        'logo_path',              // public/-relative path under storage/app/public

        // Maintenance
        'maintenance_mode',       // bool — when true, tenant routes show a maintenance page
        'maintenance_message',    // optional custom message
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
    ];

    public static function singleton(): self
    {
        $row = static::first();
        if ($row) {
            if ($row->maintenance_mode === null) {
                $row->update(['maintenance_mode' => false]);
            }
            return $row;
        }
        return static::create([
            'platform_name'    => 'Nautiqs',
            'maintenance_mode' => false,
        ]);
    }
}
