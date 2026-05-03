<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyBrand;
use App\Models\CompanyOption;
use App\Models\Quote;
use App\Models\QuoteCounter;
use App\Services\QuoteCalculator;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Spec §8 Quote Builder — two-column reactive builder with live totals
 * under 100ms per §18.2. All calculations delegated to QuoteCalculator
 * so the builder summary, the persisted Quote.totals snapshot, and the
 * PDF totals all agree bit-for-bit.
 */
class QuoteBuilder extends Component
{
    // Mode
    public ?string $quoteId = null;              // edit mode when set
    public bool $isEdit = false;

    // §8.1 Step 1 — Client
    public string $client_mode = 'existing'; // 'existing' | 'guest'
    public ?string $client_id = null;

    // §8.1 Step 2 — Model
    public ?string $brand_id = null;
    public ?string $model_id = null;

    // §8.1 Step 3 — Variant
    public ?string $variant_id = null;

    // §8.1 Step 4 — Options: selected[option_id] = qty
    public array $selectedOptions = [];
    public array $optionDiscounts = [];  // [option_id => pct]

    // §8.1 Step 5 — Custom line items
    public array $custom_items = [];

    // §8.1 Step 6 — Discounts (§9) — three explicit levels per client mockup
    public array $category_discounts = [];
    public float $boat_discount_pct = 0;
    public float $options_discount_pct = 0;
    public float $global_discount_pct = 0;

    // Summary view mode (vendor sees margin, client doesn't)
    public string $view_mode = 'vendor'; // 'vendor' | 'client'

    // §8.1 Step 7 — Trade-in (§10) — simplified: value only, deducted from total
    public bool $hasTradeIn = false;
    public float $trade_in_value = 0;

    // §15 Multi-currency
    public ?float $exchange_rate = null;

    // VAT + display mode
    public float $vat_rate = 20.0;
    public string $display_mode = 'TTC';

    // Internal notes (§11.4)
    public string $internal_notes = '';

    public function mount(?string $quoteId = null, ?string $preselectedClientId = null)
    {
        if ($quoteId) {
            $this->quoteId = $quoteId;
            $this->isEdit = true;
            $this->loadFromQuote();
        } else {
            $this->vat_rate     = (float) (auth()->user()->company?->default_vat_rate ?? 20);
            $this->display_mode = auth()->user()->company?->default_display_mode ?? 'TTC';
            if ($preselectedClientId) {
                $this->client_id = $preselectedClientId;
            }
        }
    }

    private function loadFromQuote(): void
    {
        $quote = Quote::findOrFail($this->quoteId);
        if (! $quote->isEditable()) {
            abort(403, 'Only draft quotes are editable.');
        }

        $this->client_id   = $quote->client_id;
        $this->client_mode = $quote->client_id ? 'existing' : 'guest';
        $this->model_id    = $quote->model_id;
        // Quotes store the company-tier model id, not the global one.
        $model = CompanyBoatModel::find($this->model_id);
        $this->brand_id   = $model?->company_brand_id;
        $this->variant_id = $quote->variant_id;

        $this->selectedOptions = [];
        $this->optionDiscounts = [];
        foreach (($quote->options ?? []) as $row) {
            if (!empty($row['option_id'])) {
                $this->selectedOptions[$row['option_id']] = $row['quantity'] ?? 1;
                $this->optionDiscounts[$row['option_id']] = $row['discount_pct'] ?? 0;
            }
        }

        $this->custom_items        = $quote->custom_items ?? [];
        $this->category_discounts  = $quote->category_discounts ?? [];
        $this->boat_discount_pct    = (float) ($quote->boat_discount_pct ?? 0);
        $this->options_discount_pct = (float) ($quote->options_discount_pct ?? 0);
        $this->global_discount_pct = (float) ($quote->global_discount_pct ?? 0);
        $this->exchange_rate       = $quote->exchange_rate;
        $this->vat_rate            = (float) ($quote->vat_rate ?? 20);
        $this->display_mode        = $quote->display_mode ?? 'TTC';
        $this->internal_notes      = $quote->internal_notes ?? '';

        if (is_array($quote->trade_in) && (($quote->trade_in['value'] ?? 0) > 0)) {
            $this->hasTradeIn = true;
            $this->trade_in_value = (float) ($quote->trade_in['value'] ?? 0);
        }
    }

    #[Computed]
    public function clients()
    {
        return Client::orderBy('last_name')->get();
    }

    #[Computed]
    public function brands()
    {
        // Spec §6 — only show brands the dealership has activated. Includes
        // both global-sourced (copied) and private brands.
        return CompanyBrand::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function models()
    {
        if (! $this->brand_id) return collect();
        return CompanyBoatModel::where('company_brand_id', $this->brand_id)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function variants()
    {
        if (! $this->model_id) return collect();
        // is_active=false hides cherry-picked-out variants from the builder
        // without losing the snapshot.
        return CompanyBoatVariant::where('company_model_id', $this->model_id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->get();
    }

    #[Computed]
    public function variant()
    {
        return $this->variant_id ? CompanyBoatVariant::find($this->variant_id) : null;
    }

    #[Computed]
    public function options()
    {
        if (! $this->model_id) return collect();
        return CompanyOption::where('company_model_id', $this->model_id)
            ->where('is_archived', false)
            ->orderBy('position')
            ->get()
            ->groupBy('category');
    }

    public function updatedBrandId()      { $this->model_id = null; $this->variant_id = null; $this->selectedOptions = []; }
    public function updatedModelId()      { $this->variant_id = null; $this->selectedOptions = []; }

    public function toggleOption(string $optionId): void
    {
        if (isset($this->selectedOptions[$optionId])) {
            unset($this->selectedOptions[$optionId], $this->optionDiscounts[$optionId]);
        } else {
            $this->selectedOptions[$optionId] = 1;
        }
    }

    public function addCustomItem(): void
    {
        $this->custom_items[] = [
            'category'     => 'custom_items',
            'label'        => '',
            'amount'       => 0,
            'cost'         => null,
            'discount_pct' => 0,
        ];
    }

    public function removeCustomItem(int $index): void
    {
        unset($this->custom_items[$index]);
        $this->custom_items = array_values($this->custom_items);
    }

    #[Computed]
    public function totals()
    {
        $company = auth()->user()->company;
        if (! $company) return null;

        $variant = $this->variant;
        if (! $variant) return null;

        // Build options payload
        $optionsPayload = [];
        $allOptions = CompanyOption::whereIn('_id', array_keys($this->selectedOptions))->get()->keyBy(fn ($o) => (string) $o->_id);
        foreach ($this->selectedOptions as $optionId => $qty) {
            $opt = $allOptions[$optionId] ?? null;
            if (! $opt) continue;
            $optionsPayload[] = [
                'option_id'    => (string) $opt->_id,
                'category'     => $opt->category,
                'label'        => $opt->label,
                'unit_price'   => (float) $opt->price,
                'unit_cost'    => (float) $opt->cost,
                'currency'     => $opt->currency ?? 'EUR',
                'quantity'     => (int) $qty,
                'discount_pct' => (float) ($this->optionDiscounts[$optionId] ?? 0),
            ];
        }

        return app(QuoteCalculator::class)->compute([
            'base_price'           => (float) $variant->base_price,
            'base_cost'            => (float) $variant->cost,
            'variant_currency'     => $variant->currency ?? 'EUR',
            'exchange_rate'        => $this->exchange_rate,
            'options'              => $optionsPayload,
            'custom_items'         => $this->custom_items,
            'category_discounts'   => $this->category_discounts,
            'boat_discount_pct'    => $this->boat_discount_pct,
            'options_discount_pct' => $this->options_discount_pct,
            'global_discount_pct'  => $this->global_discount_pct,
            'trade_in_value'       => $this->hasTradeIn ? (float) $this->trade_in_value : 0,
            'vat_rate'             => $this->vat_rate,
        ], $company);
    }

    public function save()
    {
        // Variant is always required. Client is required only in 'existing' mode.
        $rules = ['variant_id' => 'required'];
        $messages = ['variant_id.required' => 'Please select a boat model and variant.'];

        if ($this->client_mode === 'existing') {
            $rules['client_id'] = 'required';
            $messages['client_id.required'] = 'Please select a client or switch to Guest.';
        }

        $this->validate($rules, $messages);

        $companyId = auth()->user()->company_id;

        $client  = $this->client_mode === 'existing'
            ? Client::findOrFail($this->client_id)
            : null;
        $variant = CompanyBoatVariant::findOrFail($this->variant_id);
        $model   = CompanyBoatModel::findOrFail($variant->company_model_id);
        $brand   = CompanyBrand::find($model->company_brand_id);

        // Re-compute totals server-side (never trust client)
        $totals = $this->totals;

        // Build options snapshot from totals rows (preserves pricing)
        $optionsSnapshot = [];
        foreach ($totals['options_rows'] as $i => $row) {
            // Find matching selected option to preserve option_id
            $optionIdKeys = array_keys($this->selectedOptions);
            $optionsSnapshot[] = array_merge($row, [
                'option_id' => $optionIdKeys[$i] ?? null,
                'quantity'  => $row['quantity'],
                'discount_pct' => $row['item_discount_pct'],
            ]);
        }

        $payload = [
            'client_id'       => $client ? (string) $client->_id : null,
            'client_snapshot' => $client ? [
                'first_name'   => $client->first_name,
                'last_name'    => $client->last_name,
                'company_name' => $client->company_name,
                'email'        => $client->email,
                'phone'        => $client->phone,
                'address_line' => $client->address_line,
                'postal_code'  => $client->postal_code,
                'city'         => $client->city,
                'country'      => $client->country,
            ] : [
                // Guest quote — populated later via the Send modal
                'first_name'   => null,
                'last_name'    => null,
                'company_name' => null,
                'email'        => null,
                'phone'        => null,
                'address_line' => null,
                'postal_code'  => null,
                'city'         => null,
                'country'      => null,
                'is_guest'     => true,
            ],
            'model_id'         => (string) $model->_id,
            'model_snapshot'   => [
                'code'   => $model->code,
                'name'   => $model->name,
                'brand'  => $brand?->name,
                'source' => $model->source ?? 'global', // global (copied) | private
            ],
            'variant_id'       => (string) $variant->_id,
            'variant_snapshot' => ['name' => $variant->name, 'base_price' => (float) $variant->base_price, 'cost' => (float) $variant->cost, 'currency' => $variant->currency ?? 'EUR'],
            'included_equipment' => $variant->included_equipment ?? [],
            'options'              => $optionsSnapshot,
            'custom_items'         => $totals['custom_items_rows'],
            'category_discounts'   => $this->category_discounts,
            'boat_discount_pct'    => (float) $this->boat_discount_pct,
            'options_discount_pct' => (float) $this->options_discount_pct,
            'global_discount_pct'  => (float) $this->global_discount_pct,
            'trade_in'            => $this->hasTradeIn && $this->trade_in_value > 0
                ? ['value' => (float) $this->trade_in_value]
                : null,
            'currency'            => 'EUR',
            'exchange_rate'       => $this->exchange_rate,
            'exchange_rate_date'  => $this->exchange_rate ? now() : null,
            'vat_rate'            => (float) $this->vat_rate,
            'display_mode'        => $this->display_mode,
            'totals'              => $totals,
            'internal_notes'      => $this->internal_notes ?: null,
        ];

        if ($this->isEdit) {
            $quote = Quote::findOrFail($this->quoteId);
            $quote->update($payload);
        } else {
            $payload['company_id'] = $companyId;
            $payload['number']     = QuoteCounter::nextReference($companyId, 'quote', (int) date('Y'));
            $payload['status']     = Quote::STATUS_DRAFT;
            $payload['expires_at'] = now()->addDays(30); // default validity
            $payload['tracking']   = null;
            $quote = Quote::create($payload);
        }

        session()->flash('status', $this->isEdit ? 'Quote updated.' : 'Quote created as draft.');

        // Hard navigate via a browser event — Livewire's own redirect helpers
        // have intermittently swallowed the response and left a blank page.
        // window.location assignment in the listener is unambiguous.
        $this->dispatch('navigate-to', url: route('quotes.show', [
            'id'      => (string) $quote->_id,
            'preview' => 1,
        ]));
    }

    public function render()
    {
        return view('livewire.quote-builder');
    }
}
