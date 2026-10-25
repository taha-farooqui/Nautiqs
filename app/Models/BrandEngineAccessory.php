<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use App\Models\Concerns\HasLevelPrices;
use MongoDB\Laravel\Eloquent\Model;

/**
 * A pre-rigging kit or propeller in an engine brand's catalogue, linked to
 * engines through BrandEngine::accessory_ids.
 */
class BrandEngineAccessory extends Model
{
    use BelongsToBrand, HasLevelPrices;

    protected $connection = 'mongodb';
    protected $table = 'brand_engine_accessories';

    protected $fillable = [
        'brand_account_id',
        'managed_brand_id',
        'type',  // kit | propeller
        'label',
        'reference',
        'price',
        'vat_rate',
        'currency',
        'level_prices',  // [L1, L2, L3]; null = level %
    ];

    protected $casts = [
        'price'    => 'float',
        'vat_rate' => 'float',
    ];
}
