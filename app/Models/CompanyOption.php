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
        'price',              // EUR (post-conversion)
        'cost',               // EUR (post-conversion)
        'vat_rate',
        'currency',           // always 'EUR' going forward (display + storage)
        'position',
        'yard_option',        // option chantier — installed at dealership

        // Multi-currency import snapshot (V1 of the FX feature).
        'original_cost',          // what was on the row before conversion
        'original_cost_currency', // 'EUR' / 'USD'
        'original_price',
        'original_price_currency',
        'fx_rate_used',           // rate applied at import time (target=EUR)
        'fx_rate_date',           // when the rate was fetched

        'is_archived',
    ];

    protected $casts = [
        'price'         => 'float',
        'cost'          => 'float',
        'vat_rate'      => 'float',
        'position'      => 'integer',
        'yard_option'   => 'boolean',
        'original_cost' => 'float',
        'original_price'=> 'float',
        'fx_rate_used'  => 'float',
        'fx_rate_date'  => 'datetime',
        'is_archived'   => 'boolean',
    ];

    public function model()
    {
        return $this->belongsTo(CompanyBoatModel::class, 'company_model_id');
    }
}
