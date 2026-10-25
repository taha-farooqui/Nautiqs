<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use App\Models\Concerns\HasLevelPrices;
use MongoDB\Laravel\Eloquent\Model;

/**
 * A version of a brand's boat model ("690 · 1×200 HP"): public price HT,
 * optional exact purchase prices per level, standard equipment.
 */
class BrandVariant extends Model
{
    use BelongsToBrand, HasLevelPrices;

    protected $connection = 'mongodb';
    protected $table = 'brand_variants';

    protected $fillable = [
        'brand_account_id',
        'managed_brand_id',
        'brand_model_id',
        'name',
        'price',
        'currency',
        'level_prices',  // [L1, L2, L3] exact purchase prices; null = level %
        'included_equipment',  // [{label, type}] — plain array, no cast
        'position',
        'is_archived',
    ];

    protected $casts = [
        'price'       => 'float',
        'is_archived' => 'boolean',
        'position'    => 'integer',
    ];
}
