<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §6 — a brand inside a dealership's workspace.
 *
 * Two flavours:
 *   - source = 'global'  → snapshot of a global brand (copied on activation,
 *                          stays linked via global_brand_id so we can deliver
 *                          catalogue update notifications)
 *   - source = 'private' → wholly owned by the dealership, never shared
 */
class CompanyBrand extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'company_brands';

    public const SOURCE_GLOBAL  = 'global';
    public const SOURCE_PRIVATE = 'private';

    protected $fillable = [
        'company_id',
        'global_brand_id',   // null for private
        'source',            // global | private
        'name',
        'logo_path',
        'description',
        'is_active',         // dealer can deactivate without losing the snapshot
        'activated_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function models()
    {
        return $this->hasMany(CompanyBoatModel::class, 'company_brand_id');
    }

    public function isGlobal(): bool
    {
        return $this->source === self::SOURCE_GLOBAL;
    }

    public function isPrivate(): bool
    {
        return $this->source === self::SOURCE_PRIVATE;
    }
}
