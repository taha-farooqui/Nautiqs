<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use MongoDB\Laravel\Eloquent\Model;

/**
 * One brand inside a BrandAccount ("Nordvik" for Atlantic Marine Import). Its
 * catalogue is the BrandModel/BrandVariant/BrandOption rows (boat accounts) or
 * BrandEngine/BrandEngineAccessory rows (engine accounts) that carry its id.
 */
class ManagedBrand extends Model
{
    use BelongsToBrand;

    protected $connection = 'mongodb';
    protected $table = 'managed_brands';

    protected $fillable = [
        'brand_account_id',
        'name',
        // Optional per-brand discounts overriding the account's level %:
        // [level(1..3) => [category => percent]]; null/absent = account default.
        'level_pcts',
        'position',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(BrandAccount::class, 'brand_account_id');
    }

    public function hasOverrides(): bool
    {
        return is_array($this->level_pcts) && $this->level_pcts !== [];
    }
}
