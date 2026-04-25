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

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'company_id',
        'google_id',
        'avatar',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
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
}
