<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Platform-wide singleton: branding + default email templates that new
 * dealers inherit on signup. Lives in its own collection rather than
 * sitting on a Company row because it isn't tenant-scoped — there is
 * exactly one document, owned by the platform.
 *
 * Use `PlatformSetting::singleton()` to fetch/lazily create it.
 */
class PlatformSetting extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'platform_settings';

    protected $fillable = [
        // Branding
        'platform_name',        // shown in superadmin chrome, defaults to "Nautiqs"
        'logo_path',            // public/-relative path or full URL
        'email_sender_name',    // "From" name on system emails (password reset, verify)
        'email_sender_address', // "From" address; falls back to MAIL_FROM_ADDRESS when blank

        // Default email templates inherited by new dealers (key = template type)
        //   ['quote' => ['subject' => ..., 'body' => ...], 'order_confirmation' => [...], 'follow_up' => [...]]
        'default_email_templates',
    ];

    protected $casts = [
        'default_email_templates' => 'array',
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
