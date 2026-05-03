<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §7.3 — an option inside a dealership's workspace. Dealer can
 * override `price`, `cost`, and `position` independently of the global
 * catalogue.
 */
class CompanyOption extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'company_options';

    protected $fillable = [
        'company_id',
        'company_model_id',
        'global_option_id',   // null for private
        'source',             // global | private
        'category',
        'label',
        'price',
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
        return $this->belongsTo(CompanyBoatModel::class, 'company_model_id');
    }
}
