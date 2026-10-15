<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * A third-party add-on the dealer sells with a boat: a GPS, a sound system,
 * fishing gear. It is neither an option of the boat nor an engine, and is tied
 * to neither, so any quote can take it.
 *
 * Not to be confused with EngineAccessory (the kits and propellers sold under
 * an engine). On a quote these print as their own "Accessories" block, take
 * their own line discount and the quote-wide one, and are priced in their own
 * currency: a USD accessory is converted at quote time like an option is.
 * Quotes keep a snapshot, so editing or deleting one never changes a quote
 * already written.
 */
class Accessory extends Model
{
    use BelongsToTenant;

    public const CURRENCIES = ['EUR', 'USD'];

    protected $connection = 'mongodb';

    // $table, not $collection: this driver ignores $collection.
    protected $table = 'accessories';

    protected $fillable = [
        'company_id',
        'label',     // "Sound system Bose"
        'cost',      // purchase price HT
        'price',     // selling price HT
        'vat_rate',  // %
        'currency',  // EUR | USD
    ];

    protected $casts = [
        'cost'     => 'float',
        'price'    => 'float',
        'vat_rate' => 'float',
    ];
}
