<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 GLOBAL_BOAT_MODEL: global models per brand with unique code,
 * name, and default margin (§7.1).
 */
class GlobalBoatModel extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'global_boat_models';

    protected $fillable = [
        'brand_id',
        'code',                  // e.g. SG250
        'name',                  // commercial name
        'default_margin_pct',
        'is_archived',
    ];

    protected $casts = [
        'default_margin_pct' => 'float',
        'is_archived'        => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(GlobalBrand::class, 'brand_id');
    }

    public function variants()
    {
        return $this->hasMany(GlobalBoatVariant::class, 'model_id');
    }

    public function options()
    {
        return $this->hasMany(GlobalOption::class, 'model_id');
    }
}
