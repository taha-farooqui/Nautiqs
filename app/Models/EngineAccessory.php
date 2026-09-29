<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Something sold with an outboard engine but priced apart from it: the
 * pre-rigging kit that puts the controls at the helm (throttle box, wiring
 * harness, gauge screen) and the propeller.
 *
 * One record per product, shared by every engine it fits. Suzuki's MECA kit
 * suits twenty engines; stored once, its price changes in one place. Engines
 * point at accessories through Engine::accessory_ids.
 *
 * On a quote an accessory becomes its own line under the engine it was
 * picked for, and rides in the engine block: same block discount, same
 * margin preset. Quotes keep a snapshot, so editing or deleting an accessory
 * here never changes a quote already written.
 */
class EngineAccessory extends Model
{
    use BelongsToTenant;

    public const TYPE_KIT       = 'kit';
    public const TYPE_PROPELLER = 'propeller';
    public const TYPES          = [self::TYPE_KIT, self::TYPE_PROPELLER];

    protected $connection = 'mongodb';

    // $table, not $collection: this driver ignores $collection and falls back
    // to the class name, which is how EmailLog ended up in email_logs.
    protected $table = 'engine_accessories';

    protected $fillable = [
        'company_id',
        'type',        // kit | propeller
        'label',       // "Kit pré-rigging MECA - boîtier pupitre simple Keyless + …"
        'reference',   // manufacturer part number, optional
        'cost',        // dealer purchase price HT
        'price',       // selling price HT
        'vat_rate',    // %
        'currency',    // EUR
    ];

    protected $casts = [
        'cost'     => 'float',
        'price'    => 'float',
        'vat_rate' => 'float',
    ];

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_KIT ? __('Kit') : __('Propeller');
    }

    public function icon(): string
    {
        return $this->type === self::TYPE_KIT ? 'ri-dashboard-3-line' : 'ri-windy-line';
    }

    /** Engines this accessory is linked to, for the current dealer. */
    public function engines()
    {
        return Engine::where('accessory_ids', (string) $this->_id);
    }
}
