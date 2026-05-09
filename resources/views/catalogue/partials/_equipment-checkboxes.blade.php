{{--
    Equipment checkboxes — used by both create + edit equipment tabs.
    Posts as `included_equipment_refs[]` either inside the create-mode
    outer form or inside the edit-mode equipment form.

    Expects:
      $model            — the CompanyBoatModel (may be unsaved)
      $libraryEquipment — collection grouped by category
--}}

@php
    $checked = collect($model->included_equipment_refs ?? old('included_equipment_refs', []))
        ->mapWithKeys(fn ($r) => [$r => true]);
@endphp

<div x-data="{ activeCat: 'exterior' }">
    <div class="flex items-center gap-1 border-b border-gray-200 mb-3 overflow-x-auto">
        @foreach (\App\Models\GlobalEquipment::CATEGORIES as $key => $label)
            <button type="button" @click="activeCat = '{{ $key }}'"
                :class="activeCat === '{{ $key }}' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-3 py-2 text-xs font-semibold uppercase tracking-wide border-b-2 transition whitespace-nowrap">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @foreach (\App\Models\GlobalEquipment::CATEGORIES as $key => $label)
        @php $items = $libraryEquipment->get($key, collect()); @endphp
        <div x-show="activeCat === '{{ $key }}'" x-cloak>
            @if ($items->isEmpty())
                <p class="text-sm text-gray-500 italic">Nothing in this category yet.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                    @foreach ($items as $item)
                        @php $ref = 'global:' . (string) $item->_id; @endphp
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border {{ isset($checked[$ref]) ? 'border-primary-200 bg-primary-50/40' : 'border-gray-200 hover:bg-gray-50' }} cursor-pointer transition">
                            <input type="checkbox" name="included_equipment_refs[]" value="{{ $ref }}"
                                @checked(isset($checked[$ref]))
                                class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                            <span class="text-sm text-gray-800">{{ $item->label }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
