@php
    $t              = $this->totals;
    $variant        = $this->variant;
    $hasVariant     = (bool) $variant_id;
    $stepActive     = 'bg-primary-800 text-white';
    $stepInactive   = 'bg-gray-200 text-gray-500';
    $cardEnabled    = 'bg-white';
    $cardDisabled   = 'bg-gray-50 opacity-60 pointer-events-none select-none';
@endphp

<div class="relative grid grid-cols-1 xl:grid-cols-3 gap-6"
    wire:loading.class="opacity-90"
    x-data
    x-on:navigate-to.window="window.location.href = $event.detail.url">

    {{-- Global loading overlay (visible during any Livewire request) --}}
    <div wire:loading.flex
        class="fixed top-20 right-6 z-50 items-center gap-2 bg-primary-900 text-white px-4 py-2 rounded-full shadow-lg text-sm hidden">
        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"></circle>
            <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
        </svg>
        Updating…
    </div>

    {{-- LEFT: configuration steps (always visible) --}}
    <div class="xl:col-span-2 space-y-4">

        {{-- Step 1: Client (existing or guest) --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $cardEnabled }}">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full {{ $stepActive }} text-xs font-bold flex items-center justify-center">1</span>
                {{ __('Client') }}
            </h3>

            {{-- Mode toggle --}}
            <div class="inline-flex items-center bg-gray-100 rounded-lg p-0.5 mb-3 text-sm">
                <button type="button" wire:click="$set('client_mode', 'existing')"
                    class="px-3 py-1.5 rounded-md font-medium transition {{ $client_mode === 'existing' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                    <i class="ri-user-line"></i> {{ __('Existing client') }}
                </button>
                <button type="button" wire:click="$set('client_mode', 'guest')"
                    class="px-3 py-1.5 rounded-md font-medium transition {{ $client_mode === 'guest' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                    <i class="ri-user-search-line"></i> {{ __('Guest (one-off)') }}
                </button>
            </div>

            @if ($client_mode === 'existing')
                <select wire:model.live="client_id"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm">
                    <option value="">— {{ __('Select a client') }} —</option>
                    @foreach ($this->clients as $c)
                        <option value="{{ $c->_id }}">{{ $c->full_name }}{{ $c->company_name ? ' — ' . $c->company_name : '' }}</option>
                    @endforeach
                </select>
                @error('client_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

                {{-- Inline "add new client" — expands right inside the card so
                     all in-progress builder state is preserved. --}}
                <p class="text-xs text-gray-500 mt-2">
                    @if (! $quickClientOpen)
                        <button type="button" wire:click="openQuickClient"
                            class="text-primary-800 hover:underline">
                            <i class="ri-add-line"></i> {{ __('Add new client') }}
                        </button>
                    @else
                        <button type="button" wire:click="closeQuickClient"
                            class="text-gray-500 hover:underline">
                            <i class="ri-close-line"></i> {{ __('Cancel adding client') }}
                        </button>
                    @endif
                </p>

                @if ($quickClientOpen)
                    <div class="mt-3 rounded-lg border border-primary-200 bg-primary-50/30 p-4 space-y-3">
                        <p class="text-xs font-semibold text-primary-900 uppercase tracking-wide">{{ __('New client') }}</p>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('First name') }} *</label>
                                <input type="text" wire:model="quickClientFirstName"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                @error('quickClientFirstName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Last name') }} *</label>
                                <input type="text" wire:model="quickClientLastName"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                @error('quickClientLastName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                            <input type="email" wire:model="quickClientEmail"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            @error('quickClientEmail') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                                <input type="text" wire:model="quickClientPhone"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Company') }}</label>
                                <input type="text" wire:model="quickClientCompany" placeholder="{{ __('Optional') }}"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button type="button" wire:click="closeQuickClient"
                                class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg">
                                {{ __('Cancel') }}
                            </button>
                            <button type="button" wire:click="saveQuickClient" wire:loading.attr="disabled" wire:target="saveQuickClient"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50">
                                <i class="ri-save-line" wire:loading.remove wire:target="saveQuickClient"></i>
                                <i class="ri-loader-4-line animate-spin" wire:loading wire:target="saveQuickClient"></i>
                                {{ __('Save & select') }}
                            </button>
                        </div>
                    </div>
                @endif
            @else
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800">
                    <p class="flex items-center gap-2 font-medium">
                        <i class="ri-information-line"></i>
                        {{ __('This quote is for a guest — no client record will be created.') }}
                    </p>
                    <p class="text-xs text-amber-700 mt-1">
                        {{ __("You'll be asked for the recipient's name and email when you send the PDF.") }}
                    </p>
                </div>
            @endif
        </div>

        {{-- Step 2: Boat model --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $cardEnabled }}">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full {{ ($client_id || $client_mode === 'guest') ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">2</span>
                {{ __('Boat model') }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select wire:model.live="brand_id" class="rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm">
                    <option value="">— {{ __('Brand') }} —</option>
                    @foreach ($this->brands as $b)
                        <option value="{{ $b->_id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="model_id" @disabled(! $brand_id)
                    class="rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm disabled:bg-gray-50 disabled:text-gray-400">
                    <option value="">— {{ __('Model') }} —</option>
                    @foreach ($this->models as $m)
                        <option value="{{ $m->_id }}">{{ $m->name }} ({{ $m->code }})</option>
                    @endforeach
                </select>
            </div>
            <div wire:loading wire:target="brand_id,model_id" class="text-xs text-primary-800 mt-2">
                <i class="ri-loader-4-line animate-spin"></i> {{ __('Loading…') }}
            </div>
        </div>

        {{-- Step 3: Variant --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $model_id ? $cardEnabled : $cardDisabled }}">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full {{ $model_id ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">3</span>
                {{ __('Variant') }}
            </h3>

            @if (! $model_id)
                <p class="text-sm text-gray-400">{{ __('Choose a brand and model first.') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($this->variants as $v)
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition
                            {{ $variant_id === (string) $v->_id ? 'border-primary-800 bg-primary-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" wire:model.live="variant_id" value="{{ $v->_id }}"
                                class="text-primary-800 focus:ring-primary-800" />
                            @php
                                $vCcy   = $v->currency ?? 'EUR';
                                $vSym   = $vCcy === 'USD' ? '$' : '€';
                                $vIsFx  = $vCcy !== 'EUR';
                                $vEur   = $vIsFx
                                    ? (app(\App\Services\FxRateService::class)->convert((float) $v->base_price, $vCcy, 'EUR') ?? $v->base_price)
                                    : $v->base_price;
                            @endphp
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ $v->name }}</div>
                                <div class="text-xs text-gray-500">{{ $vCcy }} · {{ __('base price') }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900">€{{ number_format($vEur, 0, ',', ' ') }}</div>
                                @if ($vIsFx)
                                    <div class="text-[11px] text-gray-500">{{ __('was') }} {{ $vSym }}{{ number_format($v->base_price, 0, ',', ' ') }}</div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Step 4: Included equipment (read-only, auto-loaded) --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $hasVariant ? $cardEnabled : $cardDisabled }}">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full {{ $hasVariant ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">4</span>
                {{ __('Included equipment') }}
                <span class="text-xs text-gray-400 font-normal ml-1">{{ __('Auto-loaded from variant') }}</span>
            </h3>

            @if (! $hasVariant)
                <p class="text-sm text-gray-400">{{ __("Pick a variant to see what's included.") }}</p>
            @else
                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm text-gray-700">
                    @forelse ($variant->included_equipment ?? [] as $eq)
                        <li class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">
                                <i class="ri-check-line"></i> {{ $eq['label'] ?? '' }}
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">{{ __('No standard equipment defined for this variant.') }}</li>
                    @endforelse
                </ul>
            @endif
        </div>

        {{-- Step 5: Options --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $hasVariant ? $cardEnabled : $cardDisabled }}">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full {{ $hasVariant ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">5</span>
                {{ __('Options') }}
                @if ($hasVariant && count($selectedOptions) > 0)
                    <span class="text-xs text-gray-400 font-normal ml-1">{{ count($selectedOptions) }} {{ __('selected') }}</span>
                @endif
            </h3>

            @if (! $hasVariant)
                <p class="text-sm text-gray-400">{{ __('Pick a variant to see available options.') }}</p>
            @elseif ($this->options->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No options available for this model.') }}</p>
            @else
                @foreach ($this->options as $category => $items)
                    <div class="mb-4 last:mb-0">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ $category }}</h4>
                        <div class="space-y-1">
                            @foreach ($items as $opt)
                                @php $oid = (string) $opt->_id; $checked = isset($selectedOptions[$oid]); @endphp
                                <div class="flex items-center gap-3 p-2 rounded hover:bg-gray-50">
                                    {{-- Whole label area is clickable (not just the box): clicking the
                                         checkbox OR the option text toggles it. Qty/discount inputs and the
                                         price sit outside this label so editing them never toggles. --}}
                                    <label class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer">
                                        <input type="checkbox" wire:click="toggleOption('{{ $oid }}')" @checked($checked)
                                            class="text-primary-800 focus:ring-primary-800 rounded shrink-0" />
                                        <span class="flex-1 text-sm text-gray-800">{{ $opt->label }}</span>
                                    </label>
                                    @if ($checked)
                                        <input type="number" min="1" value="{{ $selectedOptions[$oid] ?? 1 }}"
                                            wire:model.live.debounce.300ms="selectedOptions.{{ $oid }}"
                                            class="w-14 text-right rounded border-gray-300 text-xs py-1 focus:border-primary-800 focus:ring-primary-800"
                                            title="{{ __('Quantity') }}" />
                                        <div class="flex items-center gap-1">
                                            <input type="number" min="0" max="100" step="0.5" placeholder="0"
                                                wire:model.live.debounce.300ms="optionDiscounts.{{ $oid }}"
                                                class="w-16 text-right rounded border-gray-300 text-xs py-1 focus:border-primary-800 focus:ring-primary-800"
                                                title="{{ __('Discount %') }}" />
                                            <span class="text-xs text-gray-400">%</span>
                                        </div>
                                    @endif
                                    @php
                                        // Options in the catalogue are already stored in EUR
                                        // (the import + manual edit both convert), so the
                                        // EUR figure is just $opt->price. Show original
                                        // beneath when the row was originally a foreign-
                                        // currency import so the dealer can audit it.
                                        $optWasFx = ! empty($opt->original_price_currency)
                                            && $opt->original_price_currency !== 'EUR'
                                            && ! empty($opt->original_price);
                                    @endphp
                                    <div class="w-28 text-right">
                                        <div class="text-sm font-medium text-gray-900">€{{ number_format($opt->price, 0, ',', ' ') }}</div>
                                        @if ($optWasFx)
                                            <div class="text-[10px] text-gray-500">
                                                {{ __('was') }} {{ $opt->original_price_currency === 'USD' ? '$' : $opt->original_price_currency }}{{ number_format($opt->original_price, 0, ',', ' ') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Step 5b: Engines (global options independent of the boat).
             Even though engines aren't tied to a specific variant, we still
             gate them behind variant selection — totals can't render until
             a variant exists, and adding engines first would be confusing. --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $hasVariant ? $cardEnabled : $cardDisabled }}">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full {{ $hasVariant ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">
                        <i class="ri-settings-3-line text-xs"></i>
                    </span>
                    {{ __('Engines & global options') }}
                    @if (count($selectedEngines) > 0)
                        <span class="text-xs text-gray-400 font-normal ml-1">{{ count($selectedEngines) }} {{ __('selected') }}</span>
                    @endif
                </h3>
                <a href="{{ route('engines.index') }}" target="_blank" class="text-xs text-primary-800 hover:underline">
                    <i class="ri-external-link-line"></i> {{ __('Manage engines') }}
                </a>
            </div>

            @if ($this->engines->isEmpty())
                <p class="text-sm text-gray-500 italic">
                    {{ __("No engines match this variant's HP. Adjust the variant or") }}
                    <a href="{{ route('engines.create') }}" class="text-primary-800 hover:underline">{{ __('add an engine') }}</a>.
                </p>
            @else
                @php
                    // Pre-shape the engines into a plain JSON-ready array so
                    // the Alpine search filter can run client-side without
                    // hitting Livewire on every keystroke.
                    $engineList = $this->engines->map(fn ($e) => [
                        'id'    => $e->id,
                        'brand' => $e->brand,
                        'code'  => $e->code,
                        'hp'    => $e->horsepower,
                        'fuel'  => $e->fuel,
                        'price' => (float) $e->price,
                        'label' => trim($e->brand . ' ' . $e->code . ($e->horsepower ? ' · ' . (int) $e->horsepower . ' HP' : '')),
                    ])->values()->all();
                    $selectedOne = ! empty($selectedEngines) ? array_key_first($selectedEngines) : null;
                    $selectedEngineLabel = null;
                    if ($selectedOne) {
                        foreach ($engineList as $row) {
                            if ($row['id'] === $selectedOne) { $selectedEngineLabel = $row['label']; break; }
                        }
                    }
                @endphp

                <div x-data="engineDropdown(@js($engineList), @js($selectedOne), @js($selectedEngineLabel))" class="relative">
                    <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border border-gray-200 bg-white hover:border-gray-300 text-left">
                        <span class="flex items-center gap-2 min-w-0">
                            <i class="ri-settings-3-line text-gray-400"></i>
                            <span class="text-sm truncate" x-text="selectedLabel || '{{ __('Select an engine…') }}'"
                                :class="selectedLabel ? 'text-gray-900' : 'text-gray-500'"></span>
                        </span>
                        <i class="ri-arrow-down-s-line text-gray-400" :class="open ? 'rotate-180' : ''"></i>
                    </button>

                    <div x-show="open" x-cloak @click.outside="open = false"
                        class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                        <div class="p-2 border-b border-gray-100">
                            <input type="search" x-model="query" x-ref="qbox" placeholder="{{ __('Search by brand or code…') }}"
                                @keydown.escape="open = false"
                                class="w-full px-3 py-1.5 text-sm rounded-md border-gray-200 focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <ul class="max-h-72 overflow-y-auto py-1">
                            <template x-for="row in filtered" :key="row.id">
                                <li>
                                    <button type="button" @click="pick(row)"
                                        :class="row.id === selectedId ? 'bg-primary-50/40' : 'hover:bg-gray-50'"
                                        class="w-full text-left px-3 py-2 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                <span x-text="row.brand"></span>
                                                <span class="text-gray-500 font-mono text-xs" x-text="row.code"></span>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <span x-text="row.hp ? Math.round(row.hp) + ' HP' : ''"></span>
                                                <template x-if="row.fuel"><span> · <span x-text="row.fuel.charAt(0).toUpperCase() + row.fuel.slice(1)"></span></span></template>
                                            </p>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900 shrink-0"
                                            x-text="'€' + row.price.toLocaleString('fr-FR')"></span>
                                    </button>
                                </li>
                            </template>
                            <template x-if="filtered.length === 0">
                                <li class="px-3 py-4 text-center text-sm text-gray-500 italic">{{ __('No engines match.') }}</li>
                            </template>
                        </ul>
                        <template x-if="selectedId">
                            <div class="p-2 border-t border-gray-100">
                                <button type="button" @click="clear()"
                                    class="w-full text-xs text-red-600 hover:underline py-1">
                                    <i class="ri-close-circle-line"></i> {{ __('Clear selection') }}
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            @endif
        </div>

        {{-- Step 6: Custom items --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $hasVariant ? $cardEnabled : $cardDisabled }}">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full {{ $hasVariant ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">6</span>
                    {{ __('Custom line items') }}
                </h3>
                @if ($hasVariant)
                    <button type="button" wire:click="addCustomItem"
                        class="text-sm font-medium text-primary-800 hover:text-primary-900">
                        <i class="ri-add-line"></i> {{ __('Add line item') }}
                    </button>
                @endif
            </div>

            @if (! $hasVariant)
                <p class="text-sm text-gray-400">{{ __('Pick a variant first.') }}</p>
            @elseif (empty($custom_items))
                <p class="text-sm text-gray-500 text-center py-3">{{ __('Add transport, preparation, admin fees, etc.') }}</p>
            @else
                <div class="grid grid-cols-12 gap-2 text-xs text-gray-500 px-1 mb-1">
                    <div class="col-span-5">{{ __('Description') }}</div>
                    <div class="col-span-3 text-right">{{ __('Amount HT') }}</div>
                    <div class="col-span-2 text-right">{{ __('Disc %') }}</div>
                    <div class="col-span-1 text-right">{{ __('Cost') }}</div>
                    <div class="col-span-1"></div>
                </div>
                <div class="space-y-2">
                    @foreach ($custom_items as $i => $ci)
                        <div class="grid grid-cols-12 gap-2 items-start" wire:key="ci-{{ $i }}">
                            <input type="text" placeholder="{{ __('Transport & preparation') }}" wire:model.live.debounce.500ms="custom_items.{{ $i }}.label"
                                class="col-span-5 rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            <input type="number" step="0.01" placeholder="0.00" wire:model.live.debounce.300ms="custom_items.{{ $i }}.amount"
                                class="col-span-3 rounded border-gray-300 text-sm text-right focus:border-primary-800 focus:ring-primary-800" />
                            <input type="number" min="0" max="100" step="0.5" placeholder="0" wire:model.live.debounce.300ms="custom_items.{{ $i }}.discount_pct"
                                class="col-span-2 rounded border-gray-300 text-sm text-right focus:border-primary-800 focus:ring-primary-800" />
                            <input type="number" step="0.01" placeholder="—" wire:model.live.debounce.300ms="custom_items.{{ $i }}.cost"
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

        {{-- Step 7: Discounts (boat / options / global) --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $hasVariant ? $cardEnabled : $cardDisabled }}">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full {{ $hasVariant ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">7</span>
                {{ __('Discounts') }}
            </h3>
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <label class="flex-1 text-sm text-gray-700">{{ __('Boat discount') }} <span class="text-xs text-gray-400">{{ __('(applies to base price only)') }}</span></label>
                    <input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.300ms="boat_discount_pct" @disabled(! $hasVariant)
                        class="w-20 text-right rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100" />
                    <span class="text-sm text-gray-500 w-3">%</span>
                    <span class="text-sm text-red-600 font-medium w-28 text-right">
                        @if ($t && ($t['boat_discount_amount'] ?? 0) > 0)
                            −€{{ number_format($t['boat_discount_amount'], 0, ',', ' ') }}
                        @else — @endif
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex-1 text-sm text-gray-700">{{ __('Options discount') }} <span class="text-xs text-gray-400">{{ __('(across all options)') }}</span></label>
                    <input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.300ms="options_discount_pct" @disabled(! $hasVariant)
                        class="w-20 text-right rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100" />
                    <span class="text-sm text-gray-500 w-3">%</span>
                    <span class="text-sm text-red-600 font-medium w-28 text-right">
                        @if ($t && ($t['options_discount_amount'] ?? 0) > 0)
                            −€{{ number_format($t['options_discount_amount'], 0, ',', ' ') }}
                        @else — @endif
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex-1 text-sm text-gray-700">{{ __('Global discount') }} <span class="text-xs text-gray-400">{{ __('(applies to the entire quote)') }}</span></label>
                    <input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.300ms="global_discount_pct" @disabled(! $hasVariant)
                        class="w-20 text-right rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100" />
                    <span class="text-sm text-gray-500 w-3">%</span>
                    <span class="text-sm text-red-600 font-medium w-28 text-right">
                        @if ($t && ($t['global_discount_amount'] ?? 0) > 0)
                            −€{{ number_format($t['global_discount_amount'], 0, ',', ' ') }}
                        @else — @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Step 8: Trade-in --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $hasVariant ? $cardEnabled : $cardDisabled }}">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-6 h-6 rounded-full {{ $hasVariant ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">8</span>
                <h3 class="font-semibold text-gray-900">{{ __('Trade-in') }} <span class="text-xs text-gray-400 font-normal">{{ __('Optional') }}</span></h3>
                <label class="ml-auto inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="hasTradeIn" @disabled(! $hasVariant) class="rounded text-primary-800 focus:ring-primary-800" />
                    {{ __('Include a trade-in') }}
                </label>
            </div>
            @if ($hasTradeIn && $hasVariant)
                <div class="flex items-center gap-3">
                    <label class="text-sm text-gray-700">{{ __('Trade-in value') }}</label>
                    <span class="text-sm text-gray-500">€</span>
                    <input type="number" step="0.01" min="0" placeholder="0.00"
                        wire:model.live.debounce.300ms="trade_in_value"
                        class="flex-1 max-w-xs rounded border-gray-300 text-sm text-right focus:border-primary-800 focus:ring-primary-800" />
                    <span class="text-xs text-gray-500">{{ __('deducted from total') }}</span>
                </div>
            @endif
        </div>

        {{-- Step 9: Quote settings + internal notes --}}
        <div class="rounded-2xl border border-gray-200 p-5 {{ $hasVariant ? $cardEnabled : $cardDisabled }}">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full {{ $hasVariant ? $stepActive : $stepInactive }} text-xs font-bold flex items-center justify-center">9</span>
                {{ __('Quote settings') }}
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('VAT rate %') }}</label>
                    <input type="number" step="0.1" wire:model.live.debounce.300ms="vat_rate" @disabled(! $hasVariant)
                        class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100" />
                    <label class="mt-2 flex items-start gap-2 cursor-pointer text-[11px] text-gray-600 leading-tight">
                        <input type="checkbox" wire:model.live="per_option_vat" @disabled(! $hasVariant)
                            class="mt-0.5 rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                        <span>
                            {{ __('Apply per-option VAT separately') }}
                            <span class="block text-[10px] text-gray-400 mt-0.5">{{ __("When on, each option's own VAT rate (from import) is used instead of this one.") }}</span>
                        </span>
                    </label>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Display mode') }}</label>
                    <select wire:model.live="display_mode" @disabled(! $hasVariant)
                        class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100">
                        <option value="TTC">{{ __('TTC (incl. VAT)') }}</option>
                        <option value="HT">{{ __('HT (excl. VAT)') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('USD→EUR rate') }}</label>
                    <input type="number" step="0.0001" wire:model.live.debounce.500ms="exchange_rate" @disabled(! $hasVariant)
                        class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100"
                        placeholder="e.g. 0.92" />
                </div>
            </div>
            {{-- Terms & conditions — printed on the PDF. Leave a field blank
                 to fall back to the system default for that line. --}}
            <div class="mt-5 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">
                    {{ __('Terms & conditions') }}
                    <span class="text-gray-400 normal-case font-normal">— {{ __('printed on the PDF') }}</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Payment') }}</label>
                        <input type="text" wire:model.live.debounce.500ms="terms_payment" @disabled(! $hasVariant)
                            class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Delivery') }}</label>
                        <input type="text" wire:model.live.debounce.500ms="terms_delivery" @disabled(! $hasVariant)
                            class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Warranty') }}</label>
                        <input type="text" wire:model.live.debounce.500ms="terms_warranty" @disabled(! $hasVariant)
                            class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Notes') }}</label>
                        <input type="text" wire:model.live.debounce.500ms="terms_notes" @disabled(! $hasVariant)
                            class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100" />
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-gray-100">
                <label class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-2">
                    {{ __('Internal notes') }}
                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                        <i class="ri-lock-2-line"></i> {{ __('Never in PDF') }}
                    </span>
                </label>
                <textarea wire:model.live.debounce.500ms="internal_notes" rows="2" @disabled(! $hasVariant)
                    class="w-full rounded border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 disabled:bg-gray-100"></textarea>
            </div>
        </div>
    </div>

    {{-- RIGHT: live financial summary (§8.3) --}}
    <div class="xl:col-span-1">
        <div class="sticky top-20 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 bg-primary-900 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold">{{ __('Summary') }}</h3>
                        <p class="text-xs text-white/70">{{ __('Live totals · updates as you edit') }}</p>
                    </div>
                    {{-- Vendor / Client view toggle --}}
                    <div class="inline-flex items-center bg-white/10 rounded-lg p-0.5 text-xs">
                        <button type="button" wire:click="$set('view_mode', 'vendor')"
                            class="px-3 py-1 rounded-md font-semibold transition {{ $view_mode === 'vendor' ? 'bg-white text-primary-900' : 'text-white/80 hover:text-white' }}">
                            {{ __('Vendor') }}
                        </button>
                        <button type="button" wire:click="$set('view_mode', 'client')"
                            class="px-3 py-1 rounded-md font-semibold transition {{ $view_mode === 'client' ? 'bg-white text-primary-900' : 'text-white/80 hover:text-white' }}">
                            {{ __('Client') }}
                        </button>
                    </div>
                </div>
            </div>

            @if (! $t)
                <div class="p-8 text-center text-sm text-gray-500">
                    <i class="ri-sailboat-line text-4xl text-gray-300 mb-3 block"></i>
                    {{ __('Select a boat variant to see totals.') }}
                </div>
            @else
                <dl class="p-5 space-y-2 text-sm">
                    {{-- Boat --}}
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('Base price (excl. VAT)') }}</dt>
                        <dd class="text-right">
                            <div class="font-medium">€{{ number_format($t['base_price_gross'], 2, ',', ' ') }}</div>
                            @if (! empty($t['base_price_currency']) && $t['base_price_currency'] !== 'EUR' && ! empty($t['base_price_original']))
                                <div class="text-[11px] text-gray-500">{{ __('was') }} {{ $t['base_price_currency'] === 'USD' ? '$' : $t['base_price_currency'] }}{{ number_format($t['base_price_original'], 2, ',', ' ') }}</div>
                            @endif
                        </dd>
                    </div>
                    @if ($t['boat_discount_amount'] > 0)
                        <div class="flex justify-between text-red-600 text-xs"><dt>{{ __('Boat discount') }} ({{ $t['boat_discount_pct'] }}%)</dt><dd>−€{{ number_format($t['boat_discount_amount'], 2, ',', ' ') }}</dd></div>
                    @endif

                    {{-- Options --}}
                    <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="text-gray-600">{{ __('Options') }} ({{ count($t['options_rows']) }})</dt><dd class="font-medium">€{{ number_format($t['options_gross'], 2, ',', ' ') }}</dd></div>
                    @if ($t['options_discount_amount'] > 0)
                        <div class="flex justify-between text-red-600 text-xs"><dt>{{ __('Options discount') }} ({{ $t['options_discount_pct'] }}%)</dt><dd>−€{{ number_format($t['options_discount_amount'], 2, ',', ' ') }}</dd></div>
                    @endif

                    {{-- Custom items --}}
                    @if ($t['custom_items_ht'] > 0)
                        <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="text-gray-600">{{ __('Custom items') }}</dt><dd class="font-medium">€{{ number_format($t['custom_items_ht'], 2, ',', ' ') }}</dd></div>
                    @endif

                    {{-- Global discount --}}
                    @if ($t['global_discount_amount'] > 0)
                        <div class="flex justify-between text-red-600 text-xs pt-2 border-t border-gray-100"><dt>{{ __('Global discount') }} ({{ $t['global_discount_pct'] }}%)</dt><dd>−€{{ number_format($t['global_discount_amount'], 2, ',', ' ') }}</dd></div>
                    @endif

                    <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="font-semibold">{{ __('Total excl. VAT') }}</dt><dd class="font-semibold">€{{ number_format($t['total_ht'], 2, ',', ' ') }}</dd></div>
                    @php
                        $bands = collect($t['vat_breakdown'] ?? [])->filter(fn ($amt) => $amt > 0.01);
                        $isMixed = $bands->count() > 1;
                    @endphp
                    <div class="flex justify-between" @if ($isMixed) title="{{ $bands->map(fn ($amt, $rate) => $rate . '% on €' . number_format($amt, 2, ',', ' '))->implode(' · ') }}" @endif>
                        <dt class="text-gray-600">
                            {{ __('VAT') }}
                            @if ($isMixed)
                                <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded ml-1">{{ __('mixed') }}</span>
                            @else
                                ({{ $t['vat_rate'] }}%)
                            @endif
                        </dt>
                        <dd class="font-medium">€{{ number_format($t['vat_amount'], 2, ',', ' ') }}</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="font-semibold">{{ __('Total incl. VAT') }}</dt><dd class="font-semibold">€{{ number_format($t['total_ttc'], 2, ',', ' ') }}</dd></div>
                    @if ($t['trade_in_deduction'] > 0)
                        <div class="flex justify-between text-amber-700 text-xs"><dt>{{ __('Trade-in deduction') }}</dt><dd>−€{{ number_format($t['trade_in_deduction'], 2, ',', ' ') }}</dd></div>
                    @endif
                    <div class="flex justify-between pt-3 border-t-2 border-gray-200">
                        <dt class="font-bold text-lg">{{ __('Net payable') }}</dt>
                        <dd class="font-bold text-lg text-primary-900">€{{ number_format($t['net_payable'], 2, ',', ' ') }}</dd>
                    </div>
                </dl>

                {{-- Margin block — only in Vendor view --}}
                @if ($view_mode === 'vendor')
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">{{ __('Margin') }}
                                @if ($t['margin_type'] === 'real')
                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 font-semibold">{{ __('REAL') }}</span>
                                @else
                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 font-semibold">{{ __('ESTIMATED') }}</span>
                                @endif
                            </span>
                            <span class="font-semibold text-gray-900">€{{ number_format($t['margin_amount'], 0, ',', ' ') }} ({{ $t['margin_pct'] }}%)</span>
                        </div>
                        @if ($t['total_cost'])
                            <div class="flex items-center justify-between mt-1 text-gray-500">
                                <span>{{ $t['margin_type'] === 'real' ? __('Total cost') : __('Estimated cost') }}</span>
                                <span class="font-medium">€{{ number_format($t['total_cost'], 0, ',', ' ') }}</span>
                            </div>
                        @endif
                        <p class="mt-1 text-gray-400">{{ __('Never shown to the client.') }}</p>
                    </div>
                @endif

                <div class="p-4 border-t border-gray-100">
                    <button type="button" wire:click="save" wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-3 rounded-lg transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="save"><i class="ri-file-pdf-line"></i></span>
                        <span wire:loading wire:target="save"><i class="ri-loader-4-line animate-spin"></i></span>
                        {{ __('Save & Generate PDF') }}
                    </button>
                </div>
            @endif
        </div>
    </div>

</div>

@once
@push('scripts')
<script>
    /**
     * Searchable single-select engine picker. Client-side filter so typing
     * doesn't hit Livewire on every keystroke; picking an item flips the
     * Livewire `selectedEngines` map via toggleEngine() actions (clearing
     * any previous pick first so the dropdown stays single-select).
     */
    function engineDropdown(rows, initialId, initialLabel) {
        return {
            rows: rows || [],
            open: false,
            query: '',
            selectedId: initialId || null,
            selectedLabel: initialLabel || '',

            get filtered() {
                const q = this.query.trim().toLowerCase();
                if (! q) return this.rows;
                return this.rows.filter(r =>
                    (r.brand || '').toLowerCase().includes(q) ||
                    (r.code || '').toLowerCase().includes(q) ||
                    (r.label || '').toLowerCase().includes(q)
                );
            },

            pick(row) {
                // Clear any previous pick first, then toggle the new one on.
                // Single-select semantics for boats with one engine slot.
                if (this.selectedId && this.selectedId !== row.id) {
                    this.$wire.toggleEngine(this.selectedId);
                }
                this.$wire.toggleEngine(row.id);
                this.selectedId = row.id;
                this.selectedLabel = row.label;
                this.open = false;
                this.query = '';
            },

            clear() {
                if (this.selectedId) {
                    this.$wire.toggleEngine(this.selectedId);
                }
                this.selectedId = null;
                this.selectedLabel = '';
                this.open = false;
            },
        };
    }
</script>
@endpush
@endonce
