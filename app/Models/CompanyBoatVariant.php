<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §7 — a variant inside a dealership's workspace. The dealer can
 * override `base_price`, `cost`, and `included_equipment` independently
 * of the global catalogue once the variant is activated.
 *
 * `is_active` lets a dealer cherry-pick which variants of an activated
 * brand they actually sell (per-variant activation).
 */
class CompanyBoatVariant extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'company_boat_variants';

    protected $fillable = [
        'company_id',
        'company_model_id',
        'global_variant_id',  // null for private
        'source',             // global | private
        'name',
        'base_price',
        'cost',
        'currency',
        'included_equipment', // [{label, type: standard|free_text}]
        'is_active',          // false = hidden from quote builder
        'is_archived',
    ];

    protected $casts = [
        'base_price'         => 'float',
        'cost'               => 'float',
        'included_equipment' => 'array',
        'is_active'          => 'boolean',
        'is_archived'        => 'boolean',
    ];

    public function model()
    {
        return $this->belongsTo(CompanyBoatModel::class, 'company_model_id');
    }
}
