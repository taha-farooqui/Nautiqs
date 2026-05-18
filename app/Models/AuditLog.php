<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Append-only record of every destructive superadmin action (delete brand,
 * suspend tenant, impersonate user, edit translation, etc.). Platform-scoped:
 * does NOT use BelongsToTenant — these rows describe platform-level activity
 * and are only ever visible inside /admin.
 *
 * `before` / `after` are JSON snapshots of the affected document. Sensitive
 * fields (password hashes, OAuth tokens) must be redacted before writing.
 */
class AuditLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'actor_email',
        'actor_role',
        'action',          // e.g. tenant.suspend, brand.delete, translation.update
        'target_type',     // e.g. Company, GlobalBrand, Translation
        'target_id',
        'target_label',    // human-readable, denormalised so the log survives target deletion
        'before',          // array | null
        'after',           // array | null
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];
}
