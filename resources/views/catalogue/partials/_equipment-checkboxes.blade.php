{{--
    Equipment checkboxes — used by both create + edit equipment tabs.
    Posts as `included_equipment_refs[]` either inside the create-mode
    outer form or inside the edit-mode equipment form.

    Refs have two shapes:
      global:<id>           → tick of a platform-library item
      custom:<cat>:<label>  → ad-hoc item the dealer pasted/typed for this boat

    The bulk-paste box lets the dealer drop a list of items and have them
    appear as already-ticked checkboxes alongside library items.

    Expects:
      $model            — the CompanyBoatModel (may be unsaved)
      $libraryEquipment — collection grouped by category
--}}

@php
    $checked = collect($model->included_equipment_refs ?? old('included_equipment_refs', []))
        ->mapWithKeys(fn ($r) => [$r => true]);

    // Parse persisted custom refs grouped by category — the partial seeds
    // Alpine with these so they re-render on page reload.
    $customByCat = [];
    foreach (($model->included_equipment_refs ?? old('included_equipment_refs', [])) as $ref) {
        if (! str_starts_with($ref, 'custom:')) continue;
        // ref shape: custom:<cat>:<label>
        $rest = substr($ref, strlen('custom:'));
        [$cat, $label] = array_pad(explode(':', $rest, 2), 2, null);
        if ($cat && $label) {
            $customByCat[$cat][] = $label;
        }
    }
@endphp

<div x-data="equipmentBoard({{ Js::from($customByCat) }})">

    {{-- ─── Bulk-paste box ─────────────────────────────────────────── --}}
    <div class="mb-4 rounded-xl border border-primary-200 bg-primary-50/40 p-4">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-primary-100 text-primary-800 flex items-center justify-center shrink-0">
                <i class="ri-clipboard-line text-lg"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900">{{ __('Paste a list of equipment') }}</p>
                <p class="text-xs text-gray-600 mb-2">
                    {{ __('One item per line. They\'ll be added to the') }}
                    <span class="font-semibold" x-text="categoryName(activeCat)"></span>
                    {{ __('category as ticked checkboxes.') }}
                </p>
                <textarea x-model="pasteBuffer" rows="3"
                    @keydown.enter="$event.shiftKey ? null : ($event.preventDefault(), commitPaste())"
                    placeholder="Bathing platform&#10;Bimini top&#10;Bow rail"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 font-mono"></textarea>
                <div class="mt-2 flex items-center gap-2">
                    <button type="button" @click="commitPaste()"
                        :disabled="pasteBuffer.trim().length === 0"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50">
                        <i class="ri-add-line"></i> {{ __('Add to') }} <span x-text="categoryName(activeCat)"></span>
                    </button>
                    <span class="text-xs text-gray-500">{{ __('Press Enter to add · Shift+Enter for a new line') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Category tabs (hidden once dealer pastes anything) ──── --}}
    <div x-show="!hasPasted" class="flex items-center gap-1 border-b border-gray-200 mb-3 overflow-x-auto">
        @foreach (\App\Models\GlobalEquipment::CATEGORIES as $key => $label)
            <button type="button" @click="activeCat = '{{ $key }}'"
                :class="activeCat === '{{ $key }}' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-3 py-2 text-xs font-semibold uppercase tracking-wide border-b-2 transition whitespace-nowrap">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ─── Per-category library grids (hidden once pasted) ─────── --}}
    <div x-show="!hasPasted">
        @foreach (\App\Models\GlobalEquipment::CATEGORIES as $key => $label)
            @php $items = $libraryEquipment->get($key, collect()); @endphp
            <div x-show="activeCat === '{{ $key }}'" x-cloak>
                @if ($items->isEmpty())
                    <p class="text-sm text-gray-500 italic">Nothing in this category yet — paste your own above.</p>
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

    {{-- ─── Pasted-items view (shown once any category has custom items) ─── --}}
    <div x-show="hasPasted" x-cloak class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-900">
                <span x-text="totalCustom"></span> pasted item<span x-text="totalCustom === 1 ? '' : 's'"></span>
            </p>
            <button type="button" @click="clearAll()"
                class="text-xs font-medium text-red-600 hover:underline">
                <i class="ri-delete-bin-line"></i> Clear all
            </button>
        </div>

        @foreach (\App\Models\GlobalEquipment::CATEGORIES as $key => $label)
            <div x-show="(customByCat['{{ $key }}'] ?? []).length > 0" x-cloak>
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ $label }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                    <template x-for="(label, i) in (customByCat['{{ $key }}'] ?? [])" :key="'{{ $key }}-' + i + '-' + label">
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-primary-200 bg-primary-50/40 cursor-pointer transition group">
                            <input type="checkbox" name="included_equipment_refs[]"
                                :value="'custom:{{ $key }}:' + label"
                                checked
                                class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                            <span class="text-sm text-gray-800 flex-1" x-text="label"></span>
                            <button type="button" @click.prevent="removeCustom('{{ $key }}', i)"
                                class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-600 transition"
                                title="Remove">
                                <i class="ri-close-line text-sm"></i>
                            </button>
                        </label>
                    </template>
                </div>
            </div>
        @endforeach
    </div>
</div>

@once
@push('scripts')
<script>
    function equipmentBoard(initialCustom) {
        const CAT_LABELS = @json(\App\Models\GlobalEquipment::CATEGORIES);

        // Pre-seed every category key with an empty array so Alpine tracks
        // them reactively from the start (adding a fresh key on a plain
        // object after init won't trigger x-show recomputes in Alpine v3).
        const seeded = {};
        for (const key of Object.keys(CAT_LABELS)) {
            seeded[key] = Array.isArray(initialCustom?.[key]) ? [...initialCustom[key]] : [];
        }

        return {
            activeCat: 'exterior',
            customByCat: seeded,
            pasteBuffer: '',

            get hasPasted() {
                return Object.values(this.customByCat).some(arr => arr && arr.length > 0);
            },
            get totalCustom() {
                return Object.values(this.customByCat).reduce((sum, arr) => sum + (arr?.length ?? 0), 0);
            },
            clearAll() {
                for (const key of Object.keys(this.customByCat)) {
                    this.customByCat[key] = [];
                }
            },

            categoryName(key) { return CAT_LABELS[key] ?? key; },

            commitPaste() {
                const lines = this.pasteBuffer
                    .split(/\r?\n/)
                    .map(s => s.trim())
                    .filter(s => s.length > 0);
                if (lines.length === 0) return;

                const current = this.customByCat[this.activeCat] ?? [];
                const existing = new Set(current.map(s => s.toLowerCase()));
                const next = [...current];
                for (const line of lines) {
                    if (existing.has(line.toLowerCase())) continue;
                    next.push(line);
                    existing.add(line.toLowerCase());
                }
                // Reassign the array reference so Alpine reactivity fires
                // even if the key was previously empty.
                this.customByCat[this.activeCat] = next;
                this.pasteBuffer = '';
            },

            removeCustom(cat, index) {
                if (! this.customByCat[cat]) return;
                const next = [...this.customByCat[cat]];
                next.splice(index, 1);
                this.customByCat[cat] = next;
            },
        };
    }
</script>
@endpush
@endonce
