<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Platform-provided option library — paid add-ons that any boat can offer
 * (e.g. "Garmin GPS chartplotter 9", "Refrigerator 40L", "Fire suppression
 * system"). Each option carries a suggested price; the dealer can override
 * it when attaching it to a specific boat or quote.
 *
 * NOT tenant-scoped.
 */
class GlobalOptionItem extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'global_option_items';

    protected $fillable = [
        'category',       // free text (e.g. "Electronics", "Comfort", "Safety")
        'label',
        'description',
        'price',
        'vat_rate',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'float',
        'vat_rate'  => 'float',
        'is_active' => 'boolean',
    ];
}
