<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use MongoDB\Laravel\Eloquent\Model;

/**
 * A boat model in a brand's catalogue ("Nordvik 690"). Its versions and
 * options carry the prices; BrandSync copies it into each connected dealer as a
 * CompanyBoatModel with brand_model_id pointing back here.
 */
class BrandModel extends Model
{
    use BelongsToBrand;

    protected $connection = 'mongodb';
    protected $table = 'brand_models';

    protected $fillable = [
        'brand_account_id',
        'managed_brand_id',
        'name',
        'code',
        'year',
        'type',
        'propulsion',
        'length_total',
        'beam',
        'draft_max',
        'weight',
        'passengers',
        'description',
        'position',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'position'    => 'integer',
    ];

    public function brand()
    {
        return $this->belongsTo(ManagedBrand::class, 'managed_brand_id');
    }

    public function variants()
    {
        return $this->hasMany(BrandVariant::class, 'brand_model_id');
    }

    public function options()
    {
        return $this->hasMany(BrandOption::class, 'brand_model_id');
    }
}
