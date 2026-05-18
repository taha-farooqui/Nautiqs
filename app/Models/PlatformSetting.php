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

        // Sign-ups
        'signups_enabled',        // bool — when false, /register returns 404

        // Email tracking
        'email_tracking_base_url',// override for config('app.tracking_base_url')

        // Maintenance
        'maintenance_mode',       // bool — when true, tenant routes show a maintenance page
        'maintenance_message',    // optional custom message
    ];

    protected $casts = [
        'signups_enabled'  => 'boolean',
        'maintenance_mode' => 'boolean',
    ];

    public static function singleton(): self
    {
        $row = static::first();
        if ($row) {
            // Backfill missing fields on rows that pre-date their addition
            // so callers don't have to coalesce NULL everywhere.
            $patch = [];
            if ($row->signups_enabled  === null) $patch['signups_enabled']  = true;
            if ($row->maintenance_mode === null) $patch['maintenance_mode'] = false;
            if ($patch) $row->update($patch);
            return $row;
        }
        return static::create([
            'platform_name'    => 'Nautiqs',
            'signups_enabled'  => true,
            'maintenance_mode' => false,
        ]);
    }
}
