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
        'global_brand_id',   // the platform brand list entry it was picked from, if any
        'position',
        'is_active',
    ];

    /**
     * A typed brand name matched against the platform brand list: the list's
     * own spelling and id when it is there, the typed name otherwise.
     *
     * @return array{name: string, global_brand_id: ?string}
     */
    public static function fromName(string $name): array
    {
        $key = mb_strtolower(trim($name));
        $global = GlobalBrand::all(['_id', 'name'])->first(fn ($g) => mb_strtolower(trim((string) $g->name)) === $key);

        return $global
            ? ['name' => (string) $global->name, 'global_brand_id' => (string) $global->_id]
            : ['name' => trim($name), 'global_brand_id' => null];
    }

    /**
     * Platform brands still free to be taken by a brand account, for the
     * pickers — names only, alphabetical.
     */
    public static function availableGlobalNames(): array
    {
        $taken = self::withoutGlobalScopes()->get(['name'])->map(fn ($b) => mb_strtolower(trim((string) $b->name)))->all();

        return GlobalBrand::orderBy('name')->get(['name'])->pluck('name')
            ->reject(fn ($n) => in_array(mb_strtolower(trim((string) $n)), $taken, true))->values()->all();
    }

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
