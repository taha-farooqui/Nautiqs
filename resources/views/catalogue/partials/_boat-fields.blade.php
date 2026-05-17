{{--
    Boat tab fields — included by both create + edit views. Expects:
      $model            — the CompanyBoatModel (may be unsaved)
      $brands           — collection for the typeahead's initial-name lookup
--}}

{{-- Status toggle --}}
<div class="flex items-center gap-3 pb-3 border-b border-gray-100">
    <label class="inline-flex items-center gap-2">
        <input type="hidden" name="is_active" value="0" />
        <input type="checkbox" name="is_active" value="1"
            @checked(old('is_active', $model->is_active ?? true))
            class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
        <span class="text-sm font-medium text-gray-700">Active</span>
    </label>
    <span class="text-xs text-gray-500">Visible in the quote builder when checked.</span>
</div>

{{-- Brand typeahead --}}
<div>
    <div x-data="brandTypeahead({{ Js::from((string) ($model->company_brand_id ?? '')) }}, {{ Js::from($model->brand?->name ?? '') }})"
         x-init="init()" class="relative">
        <label class="block text-sm font-medium text-gray-700 mb-1">Brand <span class="text-red-500">*</span></label>
        <input type="hidden" name="company_brand_id" :value="selectedId" />

        <div class="relative">
            <input type="text" x-model="query" @focus="search()" @input.debounce.200ms="search()"
                @keydown.escape="open = false"
                @keydown.arrow-down.prevent="cursor = Math.min(cursor + 1, results.length - 1)"
                @keydown.arrow-up.prevent="cursor = Math.max(cursor - 1, -1)"
                @keydown.enter.prevent="if (cursor >= 0) select(results[cursor])"
                placeholder="Type to search brands…"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 pr-9" />
            <i class="ri-search-line absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>

        <div x-show="open" x-cloak @click.outside="open = false"
            class="absolute left-0 right-0 mt-1 z-30 bg-white rounded-lg border border-gray-200 shadow-lg max-h-60 overflow-y-auto">
            <template x-for="(item, i) in results" :key="item.id">
                <button type="button" @click="select(item)"
                    :class="i === cursor ? 'bg-primary-50 text-primary-900' : 'hover:bg-gray-50'"
                    class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2">
                    <span class="truncate" x-text="item.name"></span>
                    <i class="ri-check-line text-emerald-600 shrink-0" x-show="item.id === selectedId"></i>
                </button>
            </template>
            <template x-if="results.length === 0 && query.length > 0">
                <div class="px-3 py-3 text-sm">
                    <p class="text-gray-500">No brand matches «<span class="font-semibold" x-text="query"></span>».</p>
                </div>
            </template>
        </div>

        <p class="text-xs mt-1" x-show="selectedId" x-cloak><span class="text-emerald-700">✓ Brand selected.</span></p>
        <x-input-error :messages="$errors->get('company_brand_id')" class="mt-1" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Model name <span class="text-red-500">*</span></label>
        <input type="text" name="name" required value="{{ old('name', $model->name ?? '') }}"
            placeholder="e.g. 60 OPEN LINE"
            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
        <input type="number" min="1900" max="2100" name="year"
            value="{{ old('year', $model->year ?? '') }}"
            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Boat code <span class="text-red-500">*</span></label>
        <input type="text" name="code" required value="{{ old('code', $model->code ?? '') }}"
            class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-primary-800 focus:ring-primary-800" />
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Internal code') }}
            <span class="ml-1 text-[10px] font-semibold text-primary-800 bg-primary-50 px-1.5 py-0.5 rounded uppercase tracking-wide">{{ __('Import key') }}</span>
        </label>
        <input type="text" name="internal_code" value="{{ old('internal_code', $model->internal_code ?? '') }}"
            placeholder="e.g. ANT7OB"
            class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-primary-800 focus:ring-primary-800" />
        <p class="text-[11px] text-gray-500 mt-1">
            {{ __('Used as the link key when you bulk-import options for this boat (matches the CODE MODELE column).') }}
        </p>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Default margin (%)</label>
        <input type="number" step="0.1" min="0" max="100" name="default_margin_pct"
            value="{{ old('default_margin_pct', $model->default_margin_pct ?? '') }}"
            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
    </div>
</div>

{{--
    Hull type / Propulsion / Dimensions / Capacity / Notes — hidden for
    now. Wrapped in `@if (false)` so the HTML stays in source for easy
    revival without rendering. The underlying DB columns are still on
    CompanyBoatModel; just no UI exposes them today.
--}}
@if (false)
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Hull type</label>
        <select name="type" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
            <option value="">—</option>
            @foreach (\App\Models\CompanyBoatModel::TYPES as $t)
                <option value="{{ $t }}" @selected(old('type', $model->type ?? '') === $t)>{{ ucfirst(str_replace('-', ' ', $t)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Propulsion</label>
        <select name="propulsion" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
            <option value="">—</option>
            @foreach (\App\Models\CompanyBoatModel::PROPULSIONS as $p)
                <option value="{{ $p }}" @selected(old('propulsion', $model->propulsion ?? '') === $p)>{{ ucfirst($p) }}</option>
            @endforeach
        </select>
    </div>
</div>

{{-- Dimensions --}}
<div class="pt-3 border-t border-gray-100">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Dimensions</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach ([
            'length_total' => 'Length total (m)',
            'length_hull' => 'Hull length (m)',
            'length_waterline' => 'Waterline (m)',
            'beam' => 'Beam (m)',
            'draft_min' => 'Draft min (m)',
            'draft_max' => 'Draft max (m)',
            'weight' => 'Weight (kg)',
        ] as $field => $label)
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                <input type="number" step="0.01" min="0" name="{{ $field }}"
                    value="{{ old($field, $model->{$field} ?? '') }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
        @endforeach
    </div>
</div>

{{-- Capacity grid --}}
<div class="pt-3 border-t border-gray-100">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Capacity</h3>
    @php $cap = old('capacity', $model->capacity ?? []); @endphp
    <table class="w-full max-w-xl text-sm">
        <thead>
            <tr class="text-xs text-gray-500">
                <th class="text-left font-medium pb-1"></th>
                <th class="font-medium pb-1">A</th>
                <th class="font-medium pb-1">B</th>
                <th class="font-medium pb-1">C</th>
                <th class="font-medium pb-1">D</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-gray-700 pr-3"><i class="ri-user-line"></i> Passengers</td>
                @foreach (['a','b','c','d'] as $col)
                    <td class="pr-2">
                        <input type="number" min="0" step="1"
                            name="capacity[passengers][{{ $col }}]"
                            value="{{ $cap['passengers'][$col] ?? '' }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                    </td>
                @endforeach
            </tr>
            <tr>
                <td class="text-gray-700 pr-3"><i class="ri-suitcase-line"></i> + Luggage</td>
                @foreach (['a','b','c','d'] as $col)
                    <td class="pr-2 pt-2">
                        <input type="number" min="0" step="1"
                            name="capacity[passengers_luggage][{{ $col }}]"
                            value="{{ $cap['passengers_luggage'][$col] ?? '' }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
    <textarea name="notes" rows="3"
        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">{{ old('notes', $model->notes ?? '') }}</textarea>
</div>
@endif
