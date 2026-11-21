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
     * Brand names to suggest in the pickers: the platform brand list plus any
     * name already managed by an account (so a brand typed once is remembered),
     * de-duplicated case-insensitively, alphabetical. A brand identity may be
     * managed by several accounts (one per territory), so the list is NOT
     * trimmed to "unused" names; territory uniqueness is enforced on save by
     * territoryConflicts().
     */
    public static function globalBrandNames(): array
    {
        $names = GlobalBrand::orderBy('name')->get(['name'])->pluck('name')
            ->merge(self::withoutGlobalScopes()->get(['name'])->pluck('name'))
            ->map(fn ($n) => trim((string) $n))->filter();

        return $names->unique(fn ($n) => mb_strtolower($n))
            ->sort(fn ($a, $b) => strnatcasecmp($a, $b))->values()->all();
    }

    /** Trim, collapse inner whitespace, cap at 40; empty becomes null. */
    public static function normalizeRegion($region): ?string
    {
        $r = trim(preg_replace('/\s+/u', ' ', (string) $region));

        return $r === '' ? null : mb_substr($r, 0, 40);
    }

    /**
     * Territory-aware uniqueness. For each candidate brand name, the error
     * message if ANOTHER account already manages the same brand in an
     * overlapping territory, keyed by the name. Empty array = all clear.
     *
     * Same brand name (case-insensitive):
     *  - identical (country, region)                     → duplicate  (blocked)
     *  - one side has no country, the other does         → overlap    (blocked)
     *  - same country, one country-wide + one regional   → overlap    (blocked)
     *  - same country, different regions                 → allowed
     *  - different countries                             → allowed
     *
     * @param  string[]  $names
     * @return array<string, string>
     */
    public static function territoryConflicts(array $names, ?string $country, ?string $region, ?string $exceptAccountId = null): array
    {
        $names = array_values(array_filter(array_map('trim', $names), fn ($n) => $n !== ''));
        if (! $names) return [];

        $country = $country ? strtoupper(trim($country)) : null;
        $cRkey   = ($r = self::normalizeRegion($region)) !== null ? mb_strtolower($r) : null;

        $accounts = BrandAccount::all()->keyBy(fn ($a) => (string) $a->_id);
        $byName = [];
        foreach (self::withoutGlobalScopes()->get(['name', 'brand_account_id']) as $b) {
            $aid = (string) $b->brand_account_id;
            if ($exceptAccountId !== null && $aid === (string) $exceptAccountId) continue;
            $acc = $accounts[$aid] ?? null;
            if (! $acc) continue;
            $byName[mb_strtolower(trim((string) $b->name))][] = [
                'country' => $acc->country ? strtoupper(trim((string) $acc->country)) : null,
                'rkey'    => ($er = self::normalizeRegion($acc->region)) !== null ? mb_strtolower($er) : null,
            ];
        }

        $out = [];
        foreach ($names as $name) {
            foreach ($byName[mb_strtolower($name)] ?? [] as $e) {
                if ($msg = self::territoryClashMessage($name, $country, $cRkey, $e['country'], $e['rkey'])) {
                    $out[$name] = $msg;
                    break;
                }
            }
        }

        return $out;
    }

    private static function territoryClashMessage(string $name, ?string $cCountry, ?string $cRkey, ?string $eCountry, ?string $eRkey): ?string
    {
        // Both unspecified — a plain duplicate.
        if ($cCountry === null && $eCountry === null) {
            return __('":name" is already managed with no territory set. Give that account a country before creating territory-specific ones.', ['name' => $name]);
        }
        // Exactly one has a country: an unspecified account already covers the brand everywhere.
        if (($cCountry === null) !== ($eCountry === null)) {
            return __('":name" is already managed on Nautiqs without a matching territory. Give every ":name" account its own country/region.', ['name' => $name]);
        }
        // Both have a country.
        if ($cCountry !== $eCountry) {
            return null; // different countries — fine
        }
        $country = BrandAccount::countryLabel($cCountry);
        // Same country, both country-wide.
        if ($cRkey === null && $eRkey === null) {
            return __('":name" is already managed for :country.', ['name' => $name, 'country' => $country]);
        }
        // Same country, one country-wide and one regional — they overlap.
        if (($cRkey === null) !== ($eRkey === null)) {
            return __('":name" already has a :country-wide account. Remove it, or give each ":name" :country account its own region.', ['name' => $name, 'country' => $country]);
        }
        // Same country, same region.
        if ($cRkey === $eRkey) {
            return __('":name" already exists for this territory (:country).', ['name' => $name, 'country' => $country]);
        }

        return null; // same country, different regions — fine
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
