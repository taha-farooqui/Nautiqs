<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Per-key translation override. The application's canonical translation
 * source remains lang/{locale}.json (checked into git). Rows in this
 * collection override that file's value when present, scoped by locale.
 *
 * Resetting a row deletes the document, which causes __() to fall back to
 * the file value. Nothing is destructive.
 *
 * Platform-wide for V1 — no company_id field. Adding one later for
 * per-tenant overrides is additive.
 */
class Translation extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'translations';

    protected $fillable = [
        'key',           // exact English source string as used in __()
        'locale',        // 'fr' | 'en' | …
        'value',         // override value
        'updated_by',    // user_id (superadmin)
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public $timestamps = false;
}
