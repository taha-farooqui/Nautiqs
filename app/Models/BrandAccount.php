<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * A manufacturer or importer that manages its own catalogue on Nautiqs for
 * the dealers it invites. One account manages one or several brands
 * (ManagedBrand) of a single kind: boats, or engines.
 *
 * Not tenant-scoped: brand managers reach it through their user's
 * brand_account_id, the superadmin through the admin area.
 */
class BrandAccount extends Model
{
    public const KIND_BOAT   = 'boat';
    public const KIND_ENGINE = 'engine';
    public const KINDS       = [self::KIND_BOAT, self::KIND_ENGINE];

    // Purchase-price categories each kind discounts separately.
    public const CATEGORIES = [
        self::KIND_BOAT   => ['versions', 'options'],
        self::KIND_ENGINE => ['engines', 'kits', 'propellers'],
    ];

    // Levels: 3 to start with; a brand can add more (up to MAX_LEVELS) or
    // remove the last ones. The stored "levels" array is the source of truth.
    public const LEVEL_COUNT = 3;   // default for a new account
    public const MAX_LEVELS  = 10;

    // How purchase prices are set: a % per level and category, or an exact
    // price per level typed (or imported) on every product.
    public const PRICING_PERCENT = 'percent';
    public const PRICING_EXACT   = 'exact';

    // A brand identity (e.g. "Brig") may be managed by several accounts, one
    // per territory. The territory (country + optional region) lives on the
    // account; the brand name a client sees never carries it. Country is a
    // fixed ISO-2 list so currency defaults and filtering stay reliable.
    public const COUNTRIES = [
        'FR' => 'France',  'IT' => 'Italy',       'ES' => 'Spain',        'PT' => 'Portugal',
        'DE' => 'Germany', 'NL' => 'Netherlands', 'BE' => 'Belgium',      'LU' => 'Luxembourg',
        'CH' => 'Switzerland', 'GB' => 'United Kingdom', 'IE' => 'Ireland',
        'GR' => 'Greece',  'HR' => 'Croatia',     'MT' => 'Malta',        'CY' => 'Cyprus',
        'MC' => 'Monaco',  'US' => 'United States', 'CA' => 'Canada',
        'AE' => 'United Arab Emirates', 'AU' => 'Australia',
    ];

    // Currencies the pricing pipeline supports today. Stored on the account so
    // a brand's imports/entries default to its selling currency.
    public const CURRENCIES = ['EUR', 'USD'];

    protected $connection = 'mongodb';
    protected $table = 'brand_accounts';

    protected $fillable = [
        'name',
        'kind',      // boat | engine
        // Three purchase-price levels: [{name, pct: {category: percent}}].
        // A plain array on purpose — this driver stores 'array' casts as JSON
        // strings.
        'levels',
        'pricing_mode', // percent (default) | exact
        'targets',      // [monthly_quoted => € HT, win_rate => %] — the brand's own goals, for its dashboard
        'country',      // ISO-2 (COUNTRIES key) or null = no territory set yet
        'region',       // free text, only meaningful with a country (e.g. "South")
        'currency',     // EUR (default) | USD — the account's selling currency
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function isEngine(): bool
    {
        return $this->kind === self::KIND_ENGINE;
    }

    /**
     * The account's selling currency, always a supported one. Named
     * currencyCode() on purpose: a method called currency() would collide with
     * the `currency` attribute and Eloquent would treat it as a relationship.
     */
    public function currencyCode(): string
    {
        return in_array($this->currency, self::CURRENCIES, true) ? $this->currency : 'EUR';
    }

    public function hasTerritory(): bool
    {
        return (bool) $this->country;
    }

    /** Full country name for an ISO-2 code, the code itself as fallback. */
    public static function countryLabel(?string $iso): ?string
    {
        if (! $iso) return null;
        $iso = strtoupper(trim($iso));

        return self::COUNTRIES[$iso] ?? $iso;
    }

    /** Compact territory tag: "FR" or "FR-South". Null when no country. */
    public function territoryLabel(): ?string
    {
        if (! $this->country) return null;
        $c = strtoupper((string) $this->country);

        return $this->region ? ($c . '-' . $this->region) : $c;
    }

    /** Human territory: "France" or "France · South". Null when no country. */
    public function territoryName(): ?string
    {
        if (! $this->country) return null;
        $c = self::countryLabel($this->country);

        return $this->region ? ($c . ' · ' . $this->region) : $c;
    }

    /**
     * Back-office label that tells accounts of the same brand apart, e.g.
     * "Brig · FR-South". NEVER used on a client quote or PDF — those read the
     * brand name alone.
     */
    public function displayLabel(): string
    {
        $t = $this->territoryLabel();

        return $t ? ($this->name . ' · ' . $t) : (string) $this->name;
    }

    /** Suggested currency for a country — editable afterwards. */
    public static function currencyForCountry(?string $iso): string
    {
        return strtoupper((string) $iso) === 'US' ? 'USD' : 'EUR';
    }

    public function isExactPricing(): bool
    {
        return $this->pricing_mode === self::PRICING_EXACT;
    }

    public function categories(): array
    {
        return self::CATEGORIES[$this->kind] ?? self::CATEGORIES[self::KIND_BOAT];
    }

    public function brands()
    {
        return $this->hasMany(ManagedBrand::class, 'brand_account_id');
    }

    public function managers()
    {
        return $this->hasMany(User::class, 'brand_account_id');
    }

    /**
     * The three levels, always complete: missing names and percentages fall
     * back to "Level n" and 0 %, so callers never index into a hole.
     *
     * @return array<int, array{name: string, pct: array<string, float>}>  keyed 1..3
     */
    public function levelCount(): int
    {
        $n = is_array($this->levels) ? count($this->levels) : 0;

        return $n > 0 ? min($n, self::MAX_LEVELS) : self::LEVEL_COUNT;
    }

    public function levelsResolved(): array
    {
        $stored = is_array($this->levels) ? array_values($this->levels) : [];
        $out = [];
        for ($i = 1; $i <= $this->levelCount(); $i++) {
            $row = $stored[$i - 1] ?? [];
            $pct = [];
            foreach ($this->categories() as $cat) {
                $pct[$cat] = (float) ($row['pct'][$cat] ?? 0);
            }
            $out[$i] = [
                'name' => trim((string) ($row['name'] ?? '')) ?: __('Level :n', ['n' => $i]),
                'pct'  => $pct,
            ];
        }
        return $out;
    }

    public function levelName(?int $level): string
    {
        return $this->levelsResolved()[$level]['name'] ?? '—';
    }

    /** Default levels for a new account: nothing discounted until the brand sets it. */
    public static function defaultLevels(string $kind): array
    {
        $levels = [];
        for ($i = 1; $i <= self::LEVEL_COUNT; $i++) {
            $levels[] = ['name' => 'Level ' . $i, 'pct' => array_fill_keys(self::CATEGORIES[$kind], 0)];
        }
        return $levels;
    }
}
