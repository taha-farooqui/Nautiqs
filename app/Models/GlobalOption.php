<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 GLOBAL_OPTION: global options per model with category, price,
 * cost, position.
 */
class GlobalOption extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'global_options';

    protected $fillable = [
        'model_id',
        'category',     // e.g. "CC Configuration", "Electronics"
        'label',
        'price',        // selling price excl. VAT
        'cost',
        'currency',
        'position',
        'is_archived',
    ];

    protected $casts = [
        'price'       => 'float',
        'cost'        => 'float',
        'position'    => 'integer',
        'is_archived' => 'boolean',
    ];

    public function model()
    {
        return $this->belongsTo(GlobalBoatModel::class, 'model_id');
    }
}
