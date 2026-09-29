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

        // Kits and propellers suggested with this engine on a quote: ids of
        // EngineAccessory rows. A plain embedded array — no 'array' cast,
        // which on this driver would store it as a JSON string that no
        // query can look inside.
        'accessory_ids',
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

    /** @return array<int, string> */
    public function accessoryIds(): array
    {
        $ids = $this->accessory_ids ?? [];
        if ($ids instanceof \Traversable) {
            $ids = iterator_to_array($ids);
        }

        return array_values(array_unique(array_map('strval', is_array($ids) ? $ids : [])));
    }

    /** Linked accessories, kits first, in the order they were linked. */
    public function accessories()
    {
        $ids = $this->accessoryIds();
        if (! $ids) {
            return collect();
        }

        return EngineAccessory::whereIn('_id', $ids)->get()
            ->sortBy(fn ($a) => [$a->type === EngineAccessory::TYPE_KIT ? 0 : 1, array_search((string) $a->_id, $ids, true)])
            ->values();
    }

    /**
     * Horsepower read from a model name when nothing else says it:
     * "DF140B TL/TX" → 140, "DF 2,5 S/L" → 2.5, "F300 NCA" → 300. Null for a
     * name with no plausible figure — a year like "Honda 2021" is not one.
     */
    public static function horsepowerFromCode(?string $code): ?float
    {
        if (! preg_match('/(\d+(?:[.,]\d+)?)/', (string) $code, $m)) {
            return null;
        }
        $hp = (float) str_replace(',', '.', $m[1]);

        return $hp > 0 && $hp <= 1000 ? $hp : null;
    }
}
