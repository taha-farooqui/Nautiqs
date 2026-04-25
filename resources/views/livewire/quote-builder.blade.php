@php
    $t = $this->totals;
    $variant = $this->variant;
@endphp

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    {{-- LEFT: configuration --}}
    <div class="xl:col-span-2 space-y-4">

        {{-- Step 1: Client --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">1</span>
                Client
            </h3>
            <select wire:model.live="client_id"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm">
                <option value="">— Select a client —</option>
                @foreach ($this->clients as $c)
                    <option value="{{ $c->_id }}">{{ $c->full_name }}{{ $c->company_name ? ' — ' . $c->company_name : '' }}</option>
                @endforeach
            </select>
            @error('client_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            <p class="text-xs text-gray-500 mt-2">
                <a href="{{ route('clients.create') }}" target="_blank" class="text-primary-800 hover:underline">
                    <i class="ri-add-line"></i> Add new client
                </a> (opens in a new tab)
            </p>
        </div>

        {{-- Step 2: Model --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">2</span>
                Boat model
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select wire:model.live="brand_id" class="rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm">
                    <option value="">— Brand —</option>
                    @foreach ($this->brands as $b)
                        <option value="{{ $b->_id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="model_id" @if (! $brand_id) disabled @endif
                    class="rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm disabled:bg-gray-50">
                    <option value="">— Model —</option>
                    @foreach ($this->models as $m)
                        <option value="{{ $m->_id }}">{{ $m->name }} ({{ $m->code }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Step 3: Variant --}}
        @if ($model_id)
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">3</span>
                    Variant
                </h3>
                <div class="space-y-2">
                    @foreach ($this->variants as $v)
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition
                            {{ $variant_id === (string) $v->_id ? 'border-primary-800 bg-primary-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" wire:model.live="variant_id" value="{{ $v->_id }}"
                                class="text-primary-800 focus:ring-primary-800" />
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ $v->name }}</div>
                                <div class="text-xs text-gray-500">{{ $v->currency }} · base</div>
                            </div>
                            <div class="text-right font-semibold text-gray-900">
                                €{{ number_format($v->base_price, 0, ',', ' ') }}
                            </div>
                        </label>
                    @endforeach
                </div>

                @if ($variant)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Included equipment</h4>
                        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm text-gray-700">
                            @foreach ($variant->included_equipment ?? [] as $eq)
                                <li class="flex items-center gap-2">
                                    <i class="ri-check-line text-emerald-600"></i>
                                    {{ $eq['label'] ?? '' }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 4: Options --}}
        @if ($variant_id && $this->options->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">4</span>
                    Options
                </h3>
                @foreach ($this->options as $category => $items)
                    <div class="mb-4 last:mb-0">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-gray-700">{{ $category }}</h4>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="text-gray-500">Category discount:</span>
                                <input type="number" min="0" max="100" step="0.5"
                                    wire:model.live.debounce.300ms="category_discounts.{{ $category }}"
                                    class="w-16 text-right rounded border-gray-300 text-xs py-1 focus:border-primary-800 focus:ring-primary-800" />
                                <span class="text-gray-500">%</span>
                            </div>
                        </div>
                        <div class="space-y-1">
                            @foreach ($items as $opt)
                                @php $oid = (string) $opt->_id; $checked = isset($selectedOptions[$oid]); @endphp
                                <div class="flex items-center gap-3 p-2 rounded hover:bg-gray-50">
                                    <input type="checkbox" wire:click="toggleOption('{{ $oid }}')" @checked($checked)
                                        class="text-primary-800 focus:ring-primary-800 rounded" />
                                    <div class="flex-1 text-sm text-gray-800">{{ $opt->label }}</div>
                                    @if ($checked)
                                        <input type="number" min="1" value="{{ $selectedOptions[$oid] ?? 1 }}"
                                            wire:model.live.debounce.300ms="selectedOptions.{{ $oid }}"
                                            class="w-14 text-right rounded border-gray-300 text-xs py-1 focus:border-primary-800 focus:ring-primary-800" />
                                        <input type="number" min="0" max="100" step="0.5" placeholder="disc %"
                                            wire:model.live.debounce.300ms="optionDiscounts.{{ $oid }}"
                                            class="w-16 text-right rounded border-gray-300 text-xs py-1 focus:border-primary-800 focus:ring-primary-800" />
                                    @endif
                                    <div class="w-24 text-right text-sm font-medium text-gray-900">
                                        €{{ number_format($opt->price, 0, ',', ' ') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Step 5: Custom items --}}
        @if ($variant_id)
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">5</span>
                        Custom line items
                    </h3>
                    <button type="button" wire:click="addCustomItem"
                        class="text-sm font-medium text-primary-800 hover:text-primary-900">
                        <i class="ri-add-line"></i> Add item
                    </button>
                </div>
                @if (empty($custom_items))
                    <p class="text-sm text-gray-500 text-center py-3">Add transport, preparation, admin fees, etc.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($custom_items as $i => $ci)
                            <div class="grid grid-cols-12 gap-2 items-start" wire:key="ci-{{ $i }}">
                                <input type="text" placeholder="Label" wire:model.live.debounce.500ms="custom_items.{{ $i }}.label"
                                    class="col-span-5 rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                <input type="number" step="0.01" placeholder="Amount HT" wire:model.live.debounce.300ms="custom_items.{{ $i }}.amount"
                                    class="col-span-3 rounded border-gray-300 text-sm text-right focus:border-primary-800 focus:ring-primary-800" />
                                <input type="number" min="0" max="100" step="0.5" placeholder="disc %" wire:model.live.debounce.300ms="custom_items.{{ $i }}.discount_pct"
                                    class="col-span-2 rounded border-gray-300 text-sm text-right focus:border-primary-800 focus:ring-primary-800" />
                                <input type="number" step="0.01" placeholder="cost" wire:model.live.debounce.300ms="custom_items.{{ $i }}.cost"
                                    class="col-span-1 rounded border-gray-300 text-sm text-right focus:border-primary-800 focus:ring-primary-800" />
                                <button type="button" wire:click="removeCustomItem({{ $i }})"
                                    class="col-span-1 text-red-500 hover:text-red-700 text-center">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 6: Global discount --}}
        @if ($variant_id)
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">6</span>
                    Global commercial discount
                </h3>
                <div class="flex items-center gap-3">
                    <input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.300ms="global_discount_pct"
                        class="w-24 rounded border-gray-300 text-sm text-right focus:border-primary-800 focus:ring-primary-800" />
                    <span class="text-sm text-gray-600">% off the entire quote</span>
                </div>
            </div>
        @endif

        {{-- Step 7: Trade-in --}}
        @if ($variant_id)
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">7</span>
                    <h3 class="font-semibold text-gray-900">Trade-in</h3>
                    <label class="ml-auto inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="hasTradeIn" class="rounded text-primary-800 focus:ring-primary-800" />
                        Include a trade-in
                    </label>
                </div>
                @if ($hasTradeIn)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <input type="text" placeholder="Brand" wire:model.live.debounce.500ms="trade_in.brand" class="rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        <input type="text" placeholder="Model" wire:model.live.debounce.500ms="trade_in.model" class="rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        <input type="text" placeholder="Year" wire:model.live.debounce.500ms="trade_in.year" class="rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        <input type="text" placeholder="Engine" wire:model.live.debounce.500ms="trade_in.engine" class="rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        <input type="number" placeholder="Engine hours" wire:model.live.debounce.500ms="trade_in.engine_hours" class="rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        <input type="number" step="0.01" placeholder="Trade-in value €" wire:model.live.debounce.300ms="trade_in.value" class="rounded border-gray-300 text-sm text-right focus:border-primary-800 focus:ring-primary-800" />
                        <textarea rows="2" placeholder="Description (optional)" wire:model.live.debounce.500ms="trade_in.description" class="sm:col-span-3 rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"></textarea>
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 8: Settings --}}
        @if ($variant_id)
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Quote settings</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">VAT rate %</label>
                        <input type="number" step="0.1" wire:model.live.debounce.300ms="vat_rate"
                            class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Display mode</label>
                        <select wire:model.live="display_mode" class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                            <option value="TTC">TTC (incl. VAT)</option>
                            <option value="HT">HT (excl. VAT)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">USD→EUR rate (if needed)</label>
                        <input type="number" step="0.0001" wire:model.live.debounce.500ms="exchange_rate"
                            class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"
                            placeholder="e.g. 0.92" />
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-2">
                        Internal notes
                        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                            <i class="ri-lock-2-line"></i> Never in PDF
                        </span>
                    </label>
                    <textarea wire:model.live.debounce.500ms="internal_notes" rows="2"
                        class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"></textarea>
                </div>
            </div>
        @endif
    </div>

    {{-- RIGHT: live financial summary (§8.3) --}}
    <div class="xl:col-span-1">
        <div class="sticky top-20 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 bg-primary-900 text-white">
                <h3 class="font-semibold">Summary</h3>
                <p class="text-xs text-white/70">Live totals · updates as you edit</p>
            </div>

            @if (! $t)
                <div class="p-8 text-center text-sm text-gray-500">
                    Select a boat variant to see totals.
                </div>
            @else
                <dl class="p-5 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">Base price (HT)</dt><dd class="font-medium">€{{ number_format($t['base_ht'], 2, ',', ' ') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Options (HT)</dt><dd class="font-medium">€{{ number_format($t['options_ht'], 2, ',', ' ') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Custom items (HT)</dt><dd class="font-medium">€{{ number_format($t['custom_items_ht'], 2, ',', ' ') }}</dd></div>
                    @if ($t['global_discount_amount'] > 0)
                        <div class="flex justify-between text-red-600"><dt>Global discount ({{ $t['global_discount_pct'] }}%)</dt><dd class="font-medium">−€{{ number_format($t['global_discount_amount'], 2, ',', ' ') }}</dd></div>
                    @endif
                    <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="font-semibold">Total HT</dt><dd class="font-semibold">€{{ number_format($t['total_ht'], 2, ',', ' ') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">VAT ({{ $t['vat_rate'] }}%)</dt><dd class="font-medium">€{{ number_format($t['vat_amount'], 2, ',', ' ') }}</dd></div>
                    <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="font-semibold">Total TTC</dt><dd class="font-semibold">€{{ number_format($t['total_ttc'], 2, ',', ' ') }}</dd></div>
                    @if ($t['trade_in_deduction'] > 0)
                        <div class="flex justify-between text-gray-600"><dt>Trade-in</dt><dd>−€{{ number_format($t['trade_in_deduction'], 2, ',', ' ') }}</dd></div>
                    @endif
                    <div class="flex justify-between pt-3 border-t-2 border-gray-200">
                        <dt class="font-bold text-lg">Net payable</dt>
                        <dd class="font-bold text-lg text-primary-900">€{{ number_format($t['net_payable'], 2, ',', ' ') }}</dd>
                    </div>
                </dl>

                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Margin
                            @if ($t['margin_type'] === 'real')
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 font-semibold">REAL</span>
                            @else
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 font-semibold">ESTIMATED</span>
                            @endif
                        </span>
                        <span class="font-semibold text-gray-900">€{{ number_format($t['margin_amount'], 0, ',', ' ') }} ({{ $t['margin_pct'] }}%)</span>
                    </div>
                    <p class="mt-1 text-gray-400">Never shown to the client.</p>
                </div>

                <div class="p-4 border-t border-gray-100 space-y-2">
                    <button type="button" wire:click="save('save')"
                        class="w-full inline-flex items-center justify-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2.5 rounded-lg transition">
                        <i class="ri-save-line"></i> {{ $isEdit ? 'Update quote' : 'Save as draft' }}
                    </button>
                    <button type="button" wire:click="save('save_and_download')"
                        class="w-full inline-flex items-center justify-center gap-2 bg-white border-2 border-primary-800 text-primary-800 hover:bg-primary-50 font-semibold px-4 py-2.5 rounded-lg transition">
                        <i class="ri-download-line"></i> Save &amp; download PDF
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
