<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Per-company engine library. Decoupled from any specific boat model so
 * the same engine can be attached to a quote regardless of the hull —
 * matches the old software's "Options Globales" table where Suzuki
 * outboards live and any catalogue entry / transaction can pull from.
 */
class Engine extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'engines';

    protected $fillable = [
        'company_id',
        'brand',          // Suzuki / Yamaha / Mercury / Honda / …
        'code',           // SKU like "DF200A TL/TX"
        'horsepower',     // numeric HP
        'fuel',           // petrol | diesel | electric | unknown
        'description',
        'cost',           // dealer cost (revendeur)
        'price',          // public HT
        'vat_rate',       // %
        'currency',
        'is_archived',
    ];

    protected $casts = [
        'horsepower'  => 'float',
        'cost'        => 'float',
        'price'       => 'float',
        'vat_rate'    => 'float',
        'is_archived' => 'boolean',
    ];

    /**
     * Public TTC = price * (1 + vat_rate/100). Computed not stored so
     * changes to vat_rate don't leave a stale row.
     */
    public function priceTtc(): float
    {
        return round($this->price * (1 + ($this->vat_rate ?? 0) / 100), 2);
    }
}
