<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\GlobalBoatModel;
use App\Models\GlobalBoatVariant;
use App\Models\GlobalBrand;
use App\Models\GlobalOption;
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

    // §8.1 Step 6 — Discounts (§9)
    public array $category_discounts = [];
    public float $global_discount_pct = 0;

    // §8.1 Step 7 — Trade-in (§10)
    public bool $hasTradeIn = false;
    public array $trade_in = [
        'brand' => '', 'model' => '', 'year' => '', 'engine' => '',
        'engine_hours' => '', 'description' => '', 'value' => 0,
    ];

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

        $this->client_id  = $quote->client_id;
        $this->model_id   = $quote->model_id;
        $model = GlobalBoatModel::find($this->model_id);
        $this->brand_id   = $model?->brand_id;
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
        $this->global_discount_pct = (float) ($quote->global_discount_pct ?? 0);
        $this->exchange_rate       = $quote->exchange_rate;
        $this->vat_rate            = (float) ($quote->vat_rate ?? 20);
        $this->display_mode        = $quote->display_mode ?? 'TTC';
        $this->internal_notes      = $quote->internal_notes ?? '';

        if (is_array($quote->trade_in)) {
            $this->hasTradeIn = true;
            $this->trade_in = array_merge($this->trade_in, $quote->trade_in);
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
        return GlobalBrand::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function models()
    {
        if (! $this->brand_id) return collect();
        return GlobalBoatModel::where('brand_id', $this->brand_id)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function variants()
    {
        if (! $this->model_id) return collect();
        return GlobalBoatVariant::where('model_id', $this->model_id)
            ->where('is_archived', false)
            ->get();
    }

    #[Computed]
    public function variant()
    {
        return $this->variant_id ? GlobalBoatVariant::find($this->variant_id) : null;
    }

    #[Computed]
    public function options()
    {
        if (! $this->model_id) return collect();
        return GlobalOption::where('model_id', $this->model_id)
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
        $allOptions = GlobalOption::whereIn('_id', array_keys($this->selectedOptions))->get()->keyBy(fn ($o) => (string) $o->_id);
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
            'base_price'          => (float) $variant->base_price,
            'base_cost'           => (float) $variant->cost,
            'variant_currency'    => $variant->currency ?? 'EUR',
            'exchange_rate'       => $this->exchange_rate,
            'options'             => $optionsPayload,
            'custom_items'        => $this->custom_items,
            'category_discounts'  => $this->category_discounts,
            'global_discount_pct' => $this->global_discount_pct,
            'trade_in_value'      => $this->hasTradeIn ? (float) ($this->trade_in['value'] ?? 0) : 0,
            'vat_rate'            => $this->vat_rate,
        ], $company);
    }

    public function save(string $action = 'save')
    {
        $this->validate([
            'client_id'  => 'required',
            'variant_id' => 'required',
        ], [
            'client_id.required'  => 'Please select a client.',
            'variant_id.required' => 'Please select a boat model and variant.',
        ]);

        $company = auth()->user()->company;
        $companyId = auth()->user()->company_id;

        $client  = Client::findOrFail($this->client_id);
        $variant = GlobalBoatVariant::findOrFail($this->variant_id);
        $model   = GlobalBoatModel::findOrFail($variant->model_id);
        $brand   = GlobalBrand::find($model->brand_id);

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
            'client_id'       => (string) $client->_id,
            'client_snapshot' => [
                'first_name'   => $client->first_name,
                'last_name'    => $client->last_name,
                'company_name' => $client->company_name,
                'email'        => $client->email,
                'phone'        => $client->phone,
                'address_line' => $client->address_line,
                'postal_code'  => $client->postal_code,
                'city'         => $client->city,
                'country'      => $client->country,
            ],
            'model_id'         => (string) $model->_id,
            'model_snapshot'   => ['code' => $model->code, 'name' => $model->name, 'brand' => $brand?->name, 'source' => 'global'],
            'variant_id'       => (string) $variant->_id,
            'variant_snapshot' => ['name' => $variant->name, 'base_price' => (float) $variant->base_price, 'cost' => (float) $variant->cost, 'currency' => $variant->currency ?? 'EUR'],
            'included_equipment' => $variant->included_equipment ?? [],
            'options'             => $optionsSnapshot,
            'custom_items'        => $totals['custom_items_rows'],
            'category_discounts'  => $this->category_discounts,
            'global_discount_pct' => (float) $this->global_discount_pct,
            'trade_in'            => $this->hasTradeIn ? $this->trade_in : null,
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
            $quote = Quote::create($payload);
        }

        session()->flash('status', $this->isEdit ? 'Quote updated.' : 'Quote created as draft.');

        if ($action === 'save_and_download') {
            return redirect()->route('quotes.pdf', $quote->_id);
        }
        return redirect()->route('quotes.show', $quote->_id);
    }

    public function render()
    {
        return view('livewire.quote-builder');
    }
}
