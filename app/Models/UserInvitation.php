<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Pending invite for someone to join a dealership as a sub-account.
 * Created by an admin; consumed when the invitee follows the email link
 * and sets a password. Single-use — `accepted_at` is stamped on success.
 */
class UserInvitation extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'user_invitations';

    protected $fillable = [
        'company_id',
        'email',
        'name',
        'role',                  // tenant_admin | tenant_user
        'token',                 // 64-char random; the only secret
        'expires_at',
        'invited_by_user_id',
        'invited_by_name',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
