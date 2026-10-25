<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * A brand's invitation to one dealer, and the connection it becomes.
 *
 *   invited   — sent to an email with no Nautiqs dealership behind it yet
 *   pending   — a dealership is known; waiting for its admin to answer
 *   active    — accepted: the brand's catalogue is synced into the dealer
 *   declined  — the dealer said no
 *   revoked   — the brand ended it
 *   disconnected — the dealer ended it
 *
 * Read and written explicitly by brand_account_id / company_id, never through
 * a global scope: both sides see the same record.
 */
class BrandAccess extends Model
{
    public const STATUS_INVITED      = 'invited';
    public const STATUS_PENDING      = 'pending';
    public const STATUS_ACTIVE       = 'active';
    public const STATUS_DECLINED     = 'declined';
    public const STATUS_REVOKED      = 'revoked';
    public const STATUS_DISCONNECTED = 'disconnected';

    public const OPEN_STATUSES = [self::STATUS_INVITED, self::STATUS_PENDING];

    protected $connection = 'mongodb';
    protected $table = 'brand_accesses';

    protected $fillable = [
        'brand_account_id',
        'email',               // where the invitation was sent (lower case)
        'company_id',          // null until a dealership is known
        'status',
        'level',               // 1..3
        'managed_brand_ids',   // the brands shared with this dealer
        'message',
        'invited_by_user_id',
        'invited_by_name',
        'invited_at',
        'responded_at',
        'responded_by_user_id',
        'consent_at',          // dealer agreed to share quote statistics
        'ended_at',            // revoked or disconnected
    ];

    protected $casts = [
        'level'        => 'integer',
        'invited_at'   => 'datetime',
        'responded_at' => 'datetime',
        'consent_at'   => 'datetime',
        'ended_at'     => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(BrandAccount::class, 'brand_account_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** @return array<int, string> */
    public function brandIds(): array
    {
        return array_values(array_unique(array_map('strval', (array) ($this->managed_brand_ids ?? []))));
    }
}
