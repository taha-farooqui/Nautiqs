<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 (tenant table COMPANY) + §17 (Company Settings).
 * Dealership profile — legal details, salesperson, defaults.
 * Not tenant-scoped: the company IS the tenant.
 */
class Company extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'companies';

    protected $fillable = [
        // §17.1 Company Profile & Legal Details
        'name',
        'legal_form',        // SAS, SARL, SA, EI
        'siren',             // French business registration
        'vat_number',        // Intracommunity VAT
        'address',           // Full address (single field for V1)
        'logo_path',         // PDF header image

        // §17.2 Salesperson
        'salesperson_name',
        'salesperson_phone',
        'salesperson_email',

        // §17.3 Defaults
        'default_vat_rate',      // e.g. 20.0
        'default_margin_pct',    // fallback margin
        'default_display_mode',  // HT | TTC
        'timezone',              // IANA tz, e.g. Europe/Paris — overrides app TZ for date display

        // §17.4 Margin Presets
        'margin_presets',        // ['hull' => 12, 'engine' => 8, 'options' => 15, 'custom_items' => 10]

        // Lifecycle
        'status',                // active | suspended
        'onboarded_at',
    ];

    protected $casts = [
        'default_vat_rate'     => 'float',
        'default_margin_pct'   => 'float',
        'margin_presets'       => 'array',
        'onboarded_at'         => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'company_id');
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'company_id');
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class, 'company_id');
    }

    /**
     * Resolves a margin for a given category following the spec §3 cascade:
     *   1. MARGIN_PRESET by category
     *   2. COMPANY.default_margin_pct (global fallback)
     * The other levels (real margin from cost, model-level margin) are
     * handled in Quote calculation logic.
     */
    public function marginForCategory(string $category): float
    {
        $preset = $this->margin_presets[$category] ?? null;

        if ($preset !== null) {
            return (float) $preset;
        }

        return (float) ($this->default_margin_pct ?? 0);
    }
}
