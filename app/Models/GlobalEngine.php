<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Platform-provided engine library — visible to every dealer. Dealers can
 * also add their own engines (stored on the per-company `engines` table)
 * which stay private to them. The catalogue + quote pickers merge both
 * tiers into one list.
 *
 * NOT tenant-scoped. Maintained centrally by the platform team.
 */
class GlobalEngine extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'global_engines';

    protected $fillable = [
        'brand',          // Suzuki / Yamaha / Mercury / Honda / Volvo Penta …
        'code',           // SKU — e.g. "DF200A TL/TX"
        'horsepower',
        'fuel',           // petrol | diesel | electric | unknown
        'description',
        'price',          // suggested public HT (dealer can override per quote)
        'vat_rate',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'horsepower' => 'float',
        'price'      => 'float',
        'vat_rate'   => 'float',
        'is_active'  => 'boolean',
    ];

    public function priceTtc(): float
    {
        return round($this->price * (1 + ($this->vat_rate ?? 0) / 100), 2);
    }
}
