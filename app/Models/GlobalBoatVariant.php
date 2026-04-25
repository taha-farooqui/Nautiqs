<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 GLOBAL_BOAT_VARIANT: global variants per model with base price,
 * cost, and currency.
 */
class GlobalBoatVariant extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'global_boat_variants';

    protected $fillable = [
        'model_id',
        'name',                  // e.g. "250 Sport — 2x 200HP"
        'base_price',            // excl. VAT
        'cost',
        'currency',              // USD | EUR — see §15
        'included_equipment',    // [{label, type: standard|free_text}]
        'is_archived',
    ];

    protected $casts = [
        'base_price'         => 'float',
        'cost'               => 'float',
        'included_equipment' => 'array',
        'is_archived'        => 'boolean',
    ];

    public function model()
    {
        return $this->belongsTo(GlobalBoatModel::class, 'model_id');
    }
}
