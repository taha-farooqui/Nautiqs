<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use App\Models\Concerns\HasLevelPrices;
use MongoDB\Laravel\Eloquent\Model;

/**
 * An engine in an engine brand's catalogue. accessory_ids lists the brand's
 * kits and propellers that go with it (BrandEngineAccessory ids).
 */
class BrandEngine extends Model
{
    use BelongsToBrand, HasLevelPrices;

    protected $connection = 'mongodb';
    protected $table = 'brand_engines';

    protected $fillable = [
        'brand_account_id',
        'managed_brand_id',
        'code',
        'horsepower',
        'fuel',
        'description',
        'price',
        'vat_rate',
        'currency',
        'level_prices',  // [L1, L2, L3]; null = level %
        'accessory_ids',  // BrandEngineAccessory ids — plain array
        'is_archived',
    ];

    protected $casts = [
        'price'       => 'float',
        'vat_rate'    => 'float',
        'horsepower'  => 'float',
        'is_archived' => 'boolean',
    ];

    public function accessoryIds(): array
    {
        return array_values(array_unique(array_map('strval', (array) ($this->accessory_ids ?? []))));
    }
}
