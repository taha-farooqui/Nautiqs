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
