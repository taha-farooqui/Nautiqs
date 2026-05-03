<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §7.1 — a model inside a dealership's workspace.
 *
 * `global_model_id` is set when the model originated from the platform
 * catalogue (so future catalogue updates can match by global id rather
 * than by code, which the dealer is free to rename).
 */
class CompanyBoatModel extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'company_boat_models';

    public const SOURCE_GLOBAL  = 'global';
    public const SOURCE_PRIVATE = 'private';

    protected $fillable = [
        'company_id',
        'company_brand_id',
        'global_model_id',    // null for private
        'source',             // global | private
        'code',
        'name',
        'default_margin_pct',
        'is_archived',
    ];

    protected $casts = [
        'default_margin_pct' => 'float',
        'is_archived'        => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(CompanyBrand::class, 'company_brand_id');
    }

    public function variants()
    {
        return $this->hasMany(CompanyBoatVariant::class, 'company_model_id');
    }

    public function options()
    {
        return $this->hasMany(CompanyOption::class, 'company_model_id');
    }
}
