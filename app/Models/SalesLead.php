<?php

namespace App\Models;

use App\Casts\EncryptedPii;
use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * A sales lead: a potential client a salesperson is tracking before they
 * become a real Client. Tenant-scoped like everything else. Identifying
 * fields are encrypted at rest, same as Client. "Converting" a lead creates
 * a Client from it and stamps converted_at so it drops off the active list.
 */
class SalesLead extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'sales_leads';

    public const STATUS_NEW       = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_LOST      = 'lost';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_LOST,
    ];

    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'first_name',
        'last_name',
        'company_name',
        'email',
        'phone',
        'note',                 // free text: what they want, context
        'status',               // new | contacted | qualified | lost
        'is_flagged',           // starred/highlighted by the salesperson
        'converted_client_id',  // set when turned into a Client
        'converted_at',
    ];

    protected $casts = [
        'is_flagged'   => 'boolean',
        'converted_at' => 'datetime',
        'first_name'   => EncryptedPii::class,
        'last_name'    => EncryptedPii::class,
        'email'        => EncryptedPii::class,
        'phone'        => EncryptedPii::class,
        'note'         => EncryptedPii::class,
    ];

    /** Status labels for the UI. */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_NEW       => __('New'),
            self::STATUS_CONTACTED => __('Contacted'),
            self::STATUS_QUALIFIED => __('Qualified'),
            self::STATUS_LOST      => __('Lost'),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? __('New');
    }

    public function displayName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''))
            ?: ($this->company_name ?: __('Unnamed lead'));
    }
}
