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
                    {{ __('One item per line. Each line becomes a ticked checkbox below.') }}
                </p>
                <textarea x-model="pasteBuffer" rows="3"
                    @keydown.enter="$event.shiftKey ? null : ($event.preventDefault(), commitPaste())"
                    placeholder="Bathing platform&#10;Bimini top&#10;Bow rail"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 font-mono"></textarea>
                <div class="mt-2 flex items-center gap-2">
                    <button type="button" @click="commitPaste()"
                        :disabled="pasteBuffer.trim().length === 0"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50">
                        <i class="ri-add-line"></i> {{ __('Add to list') }}
                    </button>
                    <span class="text-xs text-gray-500">{{ __('Press Enter to add · Shift+Enter for a new line') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Pasted-items list ─────────────────────────────────────── --}}
    <div x-show="hasPasted" x-cloak class="space-y-3">
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-900">
                <span x-text="totalCustom"></span> <span x-text="totalCustom === 1 ? '{{ __('pasted item') }}' : '{{ __('pasted items') }}'"></span>
            </p>
            <button type="button" @click="clearAll()"
                class="text-xs font-medium text-red-600 hover:underline">
                <i class="ri-delete-bin-line"></i> {{ __('Clear all') }}
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
            <template x-for="(label, i) in items" :key="i + '-' + label">
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-primary-200 bg-primary-50/40 cursor-pointer transition group">
                    <input type="checkbox" name="included_equipment_refs[]"
                        :value="'custom:other:' + label"
                        checked
                        class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                    <span class="text-sm text-gray-800 flex-1" x-text="label"></span>
                    <button type="button" @click.prevent="removeItem(i)"
                        class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-600 transition"
                        title="{{ __('Remove') }}">
                        <i class="ri-close-line text-sm"></i>
                    </button>
                </label>
            </template>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    function equipmentBoard(initialCustom) {
        // Flatten any previously-saved per-category data into a single list.
        // Old refs persisted as `custom:<cat>:<label>` — we preserve the
        // labels but no longer surface the category in the UI.
        const flatten = (src) => {
            if (! src) return [];
            if (Array.isArray(src)) return [...src];
            const out = [];
            for (const key of Object.keys(src)) {
                if (Array.isArray(src[key])) out.push(...src[key]);
            }
            return out;
        };

        return {
            items: flatten(initialCustom),
            pasteBuffer: '',

            get hasPasted() { return this.items.length > 0; },
            get totalCustom() { return this.items.length; },

            clearAll() { this.items = []; },

            commitPaste() {
                const lines = this.pasteBuffer
                    .split(/\r?\n/)
                    .map(s => s.trim())
                    .filter(s => s.length > 0);
                if (lines.length === 0) return;

                const existing = new Set(this.items.map(s => s.toLowerCase()));
                const next = [...this.items];
                for (const line of lines) {
                    if (existing.has(line.toLowerCase())) continue;
                    next.push(line);
                    existing.add(line.toLowerCase());
                }
                this.items = next;
                this.pasteBuffer = '';
            },

            removeItem(index) {
                const next = [...this.items];
                next.splice(index, 1);
                this.items = next;
            },
        };
    }
</script>
@endpush
@endonce
