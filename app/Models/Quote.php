<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 (QUOTE, QUOTE_OPTION, QUOTE_CUSTOM_ITEM) + §8 (builder) +
 * §10 (trade-in) + §11 (lifecycle) + §15 (multi-currency).
 *
 * Per §6.3 + §15: quotes are financial snapshots. All prices, margins,
 * discounts and exchange rate are captured at creation and never mutate
 * when the catalogue updates. Mongo embedded documents make this natural.
 */
class Quote extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'quotes';

    // §11.2 statuses
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT  = 'sent';
    public const STATUS_WON   = 'won';
    public const STATUS_LOST  = 'lost';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_WON,
        self::STATUS_LOST,
    ];

    protected $fillable = [
        'company_id',
        'number',            // Q-YYYY-NNN (§11.1)
        'status',
        'client_id',

        // Client snapshot — frozen at creation so an edit/delete of the
        // client later doesn't mutate sent quotes.
        'client_snapshot',   // [first_name, last_name, company_name, email, phone, address...]

        // Boat
        'model_id',          // company_boat_model or global model ref
        'model_snapshot',    // [code, name, brand, source]
        'variant_id',
        'variant_snapshot',  // [name, base_price, cost, currency]

        // §7.2 included equipment snapshot
        'included_equipment', // [ {label, type: standard|free_text}, ... ]

        // §8.1 step 4 + §9: options with per-line pricing + discount
        'options',           // [ { label, category, unit_price, quantity, discount_pct, line_total, ... }, ... ]

        // §8.1 step 5: custom line items (transport, preparation, fees)
        'custom_items',      // [ { label, category, amount, discount_pct, ... }, ... ]

        // §9 Discounts — split into three explicit levels per client mockup
        'category_discounts',   // ['CC Configuration' => 5.0, ...] (still supported)
        'boat_discount_pct',    // discount on hull / base price only
        'options_discount_pct', // discount applied across all options
        'global_discount_pct',  // discount on the entire quote

        // §10 Trade-in
        'trade_in',          // [brand, model, year, engine, engine_hours, description, value]

        // §15 Multi-currency
        'currency',          // 'EUR' (display)
        'exchange_rate',     // USD->EUR snapshot
        'exchange_rate_date',

        // §8.3 Live financial summary (persisted snapshot)
        'vat_rate',           // e.g. 20.0
        'display_mode',       // HT | TTC
        'totals',             // [base_ht, options_ht, custom_items_ht, discount_total, subtotal_ht, vat_amount, total_ht, total_ttc, trade_in_deduction, net_payable, total_cost, margin_amount, margin_pct, margin_type: real|estimated]

        // Internal
        'internal_notes',     // §11.4 — never in PDF

        // Terms & conditions (override the hardcoded PDF defaults per quote).
        // Blank/null → PDF falls back to the company default copy.
        'terms',              // [payment, delivery, warranty]

        // Soft-delete / trash. Set when the user moves the quote to Trash;
        // global scope in BelongsToTenant filters these out. The trash
        // controller restores by setting it back to null.
        'trashed_at',

        // Validity (mockup-driven)
        'expires_at',         // when the quote offer expires; defaults to created_at + 30d

        // Email open-tracking (mockup-driven; populated when email module fires)
        'tracking',           // [open_count: int, first_opened_at, last_opened_at]

        // Lifecycle
        'sent_at',
        'won_at',
        'lost_at',
        'order_confirmation_number',  // BC-YYYY-NNN, set when Won → BC generated
        'order_confirmation_at',
        'duplicated_from',             // reference of the quote this was duplicated from (§11.3)

        // Multi-user attribution. We snapshot the name at creation so the
        // label survives deactivation of the user record.
        'created_by_user_id',
        'created_by_name',
    ];

    protected $casts = [
        'client_snapshot'    => 'array',
        'model_snapshot'     => 'array',
        'variant_snapshot'   => 'array',
        'included_equipment' => 'array',
        'options'            => 'array',
        'custom_items'       => 'array',
        'category_discounts' => 'array',
        'trade_in'           => 'array',
        'totals'             => 'array',
        'tracking'           => 'array',
        'terms'              => 'array',
        'trashed_at'         => 'datetime',
        'boat_discount_pct'    => 'float',
        'options_discount_pct' => 'float',
        'global_discount_pct'  => 'float',
        'vat_rate'           => 'float',
        'exchange_rate'      => 'float',
        'exchange_rate_date' => 'datetime',
        'expires_at'         => 'datetime',
        'sent_at'            => 'datetime',
        'won_at'             => 'datetime',
        'lost_at'            => 'datetime',
        'order_confirmation_at' => 'datetime',
    ];

    /**
     * Filter out trashed quotes from every default query. Trash views opt in
     * via withTrashed() / onlyTrashed() below. Mongo doesn't ship Laravel's
     * SoftDeletes trait, so this is the hand-rolled equivalent for one model.
     */
    public static function bootQuote(): void
    {
        static::addGlobalScope('not_trashed', function ($builder) {
            $builder->whereNull('trashed_at');
        });
    }

    public function scopeWithTrashed($q)
    {
        return $q->withoutGlobalScope('not_trashed');
    }

    public function scopeOnlyTrashed($q)
    {
        return $q->withoutGlobalScope('not_trashed')->whereNotNull('trashed_at');
    }

    public function isTrashed(): bool
    {
        return $this->trashed_at !== null;
    }

    public function trash(): void
    {
        $this->update(['trashed_at' => now()]);
    }

    public function untrash(): void
    {
        $this->update(['trashed_at' => null]);
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canGenerateOrderConfirmation(): bool
    {
        return $this->status === self::STATUS_WON && empty($this->order_confirmation_number);
    }

    /**
     * Days until expiry (negative if already expired). Null if no expires_at.
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expires_at) return null;
        return now()->diffInDays($this->expires_at, false);
    }

    public function isExpired(): bool
    {
        $d = $this->daysUntilExpiry();
        return $d !== null && $d < 0;
    }

    public function isExpiringSoon(int $threshold = 3): bool
    {
        $d = $this->daysUntilExpiry();
        return $d !== null && $d >= 0 && $d <= $threshold;
    }

    public function openCount(): int
    {
        return (int) ($this->tracking['open_count'] ?? 0);
    }

    /**
     * Display name of whoever created this quote. Falls back to the live
     * User record's name if the snapshot is empty (older quotes), then to
     * "—" so the UI never crashes on a deleted-and-purged user.
     */
    public function creatorName(): ?string
    {
        if (! empty($this->created_by_name)) return $this->created_by_name;
        if ($this->created_by_user_id) {
            $u = User::find($this->created_by_user_id);
            if ($u) return $u->name;
        }
        return null;
    }
}
