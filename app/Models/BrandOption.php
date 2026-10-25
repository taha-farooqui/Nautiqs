<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use App\Models\Concerns\HasLevelPrices;
use MongoDB\Laravel\Eloquent\Model;

/**
 * An option on a brand's boat model, priced like a version.
 */
class BrandOption extends Model
{
    use BelongsToBrand, HasLevelPrices;

    protected $connection = 'mongodb';
    protected $table = 'brand_options';

    protected $fillable = [
        'brand_account_id',
        'managed_brand_id',
        'brand_model_id',
        'category',
        'label',
        'description',
        'price',
        'vat_rate',
        'currency',
        'level_prices',  // [L1, L2, L3]; null = level %
        'position',
        'is_archived',
    ];

    protected $casts = [
        'price'       => 'float',
        'vat_rate'    => 'float',
        'is_archived' => 'boolean',
        'position'    => 'integer',
    ];
}
