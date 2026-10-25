<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    public const ROLE_SUPERADMIN   = 'superadmin';
    public const ROLE_TENANT_ADMIN = 'tenant_admin';
    public const ROLE_TENANT_USER  = 'tenant_user';
    // A boat or engine brand's staff: manage that account's catalogue, its
    // dealers' access, and see their quotes (client identity hidden).
    public const ROLE_BRAND_MANAGER = 'brand_manager';

    protected $fillable = [
        'name',
        'email',
        'phone',           // shown as "your contact" on quotes this user writes
        'password',
        'role',
        'company_id',
        'brand_account_id', // brand managers only; they have no company
        'google_id',
        'avatar',
        'email_verified_at',
        'is_active',
        'invited_by_user_id',
        'invitation_accepted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'invitation_accepted_at'  => 'datetime',
            'password'                => 'hashed',
            'is_active'               => 'boolean',
        ];
    }

    /**
     * Treat a missing/null is_active as active so existing user rows from
     * before this feature don't get locked out.
     */
    public function isActive(): bool
    {
        return $this->is_active === null || (bool) $this->is_active;
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === self::ROLE_TENANT_ADMIN;
    }

    public function isTenantUser(): bool
    {
        return $this->role === self::ROLE_TENANT_USER;
    }

    public function belongsToTenant(): bool
    {
        return in_array($this->role, [self::ROLE_TENANT_ADMIN, self::ROLE_TENANT_USER], true);
    }

    public function isBrandManager(): bool
    {
        return $this->role === self::ROLE_BRAND_MANAGER;
    }

    public function brandAccount()
    {
        return $this->belongsTo(BrandAccount::class, 'brand_account_id');
    }
}
