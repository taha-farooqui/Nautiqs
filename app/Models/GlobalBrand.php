<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 (platform-level) BRAND: global boat brands maintained by the
 * platform owner (e.g. Brig, Jeanneau, Quicksilver).
 */
class GlobalBrand extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'brands';

    protected $fillable = [
        'name',
        'logo_path',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active'     => 'boolean',
    ];

    public function models()
    {
        return $this->hasMany(GlobalBoatModel::class, 'brand_id');
    }
}
