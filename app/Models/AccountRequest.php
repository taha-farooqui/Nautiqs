<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Public registration requests from the login page. Platform-level (no
 * company yet — approving one is what creates the Company + admin user),
 * so deliberately NOT tenant-scoped. Superadmin reviews them under
 * /admin/account-requests; approving provisions the dealership exactly
 * like /admin/dealers/create and emails the requester a setup link.
 */
class AccountRequest extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'account_requests';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name',                 // requester full name
        'email',
        'company_name',
        'phone',
        'message',              // optional free text ("tell us about your dealership")
        'status',               // pending | approved | rejected
        'handled_at',
        'handled_by_name',      // superadmin who approved/rejected (denormalised)
        'created_company_id',   // set on approval — links to the provisioned Company
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
