<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Spec §3 CLIENT (tenant): customer contacts with internal notes.
 * Fields drawn from §12 (PDF client details: full name, email, phone, address)
 * plus §11.4 (internal notes — never visible in any PDF or email).
 */
class Client extends Model
{
    use BelongsToTenant;

    protected $connection = 'mongodb';
    protected $collection = 'clients';

    protected $fillable = [
        'company_id',

        // §12 PDF visible
        'first_name',
        'last_name',
        'company_name',    // optional — when the client is a business
        'email',
        'phone',
        'address_line',
        'postal_code',
        'city',
        'country',

        // §11.4 internal — never in any PDF or email
        'internal_notes',
        'navigation_area',  // where they sail — free text
        'current_boat',     // what they own today — free text
        'lead_source',      // how they found us (see LEAD_SOURCES)
    ];

    /**
     * The built-in "how did this lead reach us" list. Dealers can add their
     * own entries (a local boat show, a specific partner…) — those are kept
     * per-company on COMPANY.custom_lead_sources and merged in by
     * Company::leadSources(), so one dealership's additions never leak into
     * another's dropdown.
     */
    public const LEAD_SOURCES = [
        'Website',
        'Google Ads',
        'Social Media',
        'Boat Show / Event',
        'Marketplace',
        'Referral',
        'Partner / Broker',
        'Walk-in',
        'Phone Call',
        'Email',
        'Existing Customer',
        'Prospecting',
        'Other',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class, 'client_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name
            ? $this->company_name . ' (' . $this->full_name . ')'
            : $this->full_name;
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line,
            trim(($this->postal_code ?? '') . ' ' . ($this->city ?? '')),
            $this->country,
        ])->filter()->implode(', ');
    }
}
