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
        'label_en',           // optional English label (filled from import)
        'brand',              // optional, e.g. Mercury-specific option
        'code',               // dealer SKU — e.g. ANT7OB_TRA_0001 (upsert key)
        'price',
        'cost',
        'vat_rate',
        'currency',
        'position',
        'yard_option',        // option chantier — installed at dealership
        'is_archived',
    ];

    protected $casts = [
        'price'       => 'float',
        'cost'        => 'float',
        'vat_rate'    => 'float',
        'position'    => 'integer',
        'yard_option' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function model()
    {
        return $this->belongsTo(CompanyBoatModel::class, 'company_model_id');
    }
}
