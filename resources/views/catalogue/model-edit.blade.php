@php
    // The editor is shared by create + edit. In create mode the entire page
    // is one form so versions / options / equipment all save together with
    // the boat. In edit mode each tab has its own per-row inline forms,
    // which is faster for one-off price tweaks.
    $isNew       = ! $model->exists;
    $headerLabel = $isNew
        ? 'Add boat'
        : (($model->brand?->name ? $model->brand->name . ' · ' : '') . $model->name);
@endphp
<x-app-layout :title="$isNew ? 'Add boat' : $model->name" :header="$headerLabel">

    {{-- Toast --}}
    @if (session('status') || $errors->any())
        <div class="fixed top-20 right-6 z-50 space-y-2 w-full max-w-sm">
            @if (session('status'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                    x-transition.opacity
                    class="rounded-lg border border-emerald-200 bg-white shadow-lg px-4 py-3 flex items-start gap-3">
                    <i class="ri-checkbox-circle-fill text-emerald-600 text-xl shrink-0"></i>
                    <p class="flex-1 text-sm text-gray-800">{{ session('status') }}</p>
                </div>
            @endif
            @if ($errors->any())
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 7000)"
                    x-transition.opacity
                    class="rounded-lg border border-red-200 bg-white shadow-lg px-4 py-3 flex items-start gap-3">
                    <i class="ri-error-warning-fill text-red-600 text-xl shrink-0"></i>
                    <p class="flex-1 text-sm text-gray-800">{{ $errors->first() }}</p>
                </div>
            @endif
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3 text-sm text-gray-500">
        <a href="{{ route('catalogue.models') }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to catalogue') }}
        </a>
        @if (! $isNew)
            <span>·</span>
            <span class="font-mono px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">{{ $model->code }}</span>
            @if ($variants->isEmpty())
                <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[11px] font-semibold">{{ __('Draft') }}</span>
            @else
                <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[11px] font-semibold">{{ __('Active') }}</span>
            @endif
        @endif
    </div>

@if ($isNew)
    {{-- ════════════════════════════════════════════════════════════════
         CREATE MODE: one form wraps everything. Single Save persists
         boat + versions + options (custom & library) + equipment.
         ════════════════════════════════════════════════════════════════ --}}
    <form method="POST" action="{{ route('catalogue.models.store') }}"
          x-data="boatCreator()"
          @input="if ($event.target.name === 'name') boatName = $event.target.value"
          class="space-y-4">
        @csrf

        <div x-data="{ tab: 'boat' }">
            {{-- Tabs --}}
            <div class="mb-4 border-b border-gray-200 flex items-center gap-1 overflow-x-auto">
                <button type="button" @click="tab = 'boat'"
                    :class="tab === 'boat' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                    <i class="ri-sailboat-line"></i> {{ __('Boat') }}
                </button>
                <button type="button" @click="tab = 'versions'"
                    :class="tab === 'versions' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                    <i class="ri-list-check-2"></i> {{ __('Versions') }}
                    <span class="text-xs text-gray-400">(<span x-text="versions.length"></span>)</span>
                </button>
                <button type="button" @click="tab = 'options'"
                    :class="tab === 'options' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                    <i class="ri-add-circle-line"></i> {{ __('Options') }}
                </button>
            </div>

            {{-- Boat tab --}}
            <section x-show="tab === 'boat'" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                @include('catalogue.partials._boat-fields', ['model' => $model, 'brands' => $brands])
            </section>

            {{-- Versions tab — Alpine repeater --}}
            <section x-show="tab === 'versions'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">{{ __('Versions') }}</h2>
                <p class="text-xs text-gray-500 mb-4">{{ __('A version is a specific configuration (e.g. "2× 200HP"). Add at least one so you can sell this boat.') }}</p>

                <template x-for="(v, i) in versions" :key="i">
                    <div class="border border-gray-200 rounded-lg p-3 mb-3">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div class="md:col-span-5">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Version name') }} *</label>
                                <input type="text" :name="`versions[${i}][name]`" x-model="v.name"
                                    placeholder="e.g. 2x 200HP"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Public HT') }} *</label>
                                <input type="number" step="0.01" min="0" :name="`versions[${i}][base_price]`" x-model="v.base_price"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Cost') }}</label>
                                <input type="number" step="0.01" min="0" :name="`versions[${i}][cost]`" x-model="v.cost"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                                <select :name="`versions[${i}][currency]`" x-model="v.currency"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                            <div class="md:col-span-1">
                                <button type="button" @click="versions.splice(i, 1)"
                                    class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Equipment chips + add button --}}
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {{ __('Included equipment') }}
                                    <span class="text-gray-400 normal-case font-normal">(<span x-text="v.equipment.length"></span>)</span>
                                </p>
                                <button type="button" @click="openEquipmentModal(i)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-primary-50 hover:bg-primary-100 text-primary-800 rounded-lg">
                                    <i class="ri-add-line"></i> {{ __('Add equipment') }}
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(eq, ei) in v.equipment" :key="ei + '-' + eq">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs border border-emerald-200 group">
                                        <i class="ri-check-line text-emerald-600"></i>
                                        <span x-text="eq"></span>
                                        <input type="hidden" :name="`versions[${i}][equipment][]`" :value="eq" />
                                        <button type="button" @click="v.equipment.splice(ei, 1)"
                                            class="opacity-50 hover:opacity-100 text-emerald-700 hover:text-red-600 ml-1">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </span>
                                </template>
                                <template x-if="v.equipment.length === 0">
                                    <span class="text-xs text-gray-400 italic">{{ __('No equipment yet.') }}</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <button type="button" @click="addVersion()"
                    class="text-sm font-medium text-primary-800 hover:underline mt-2">
                    <i class="ri-add-line"></i> {{ __('Add version') }}
                </button>

                {{-- Equipment modal — pre-checked current items, sortable, with a paste box for new items. --}}
                <div x-show="equipmentModalOpen" x-cloak x-transition.opacity
                    @keydown.escape.window="closeEquipmentModal()"
                    class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
                    <div @click.outside="closeEquipmentModal()"
                        class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                                <i class="ri-tools-line"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900">{{ __('Included equipment') }}</h3>
                                <p class="text-xs text-gray-500">{{ __('Drag to reorder · untick to remove · paste below to add more.') }}</p>
                            </div>
                            <button type="button" @click="closeEquipmentModal()"
                                class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg">
                                <i class="ri-close-line text-xl"></i>
                            </button>
                        </div>

                        <div class="p-5 space-y-4 overflow-y-auto">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                                    {{ __('Current items') }} <span class="text-gray-400 font-normal normal-case">(<span x-text="equipmentWorkingList.length"></span>)</span>
                                </p>
                                <template x-if="equipmentWorkingList.length === 0">
                                    <p class="text-xs text-gray-400 italic">{{ __('No items yet — paste some below.') }}</p>
                                </template>
                                <ul x-ref="equipmentSortableList" class="space-y-1.5">
                                    <template x-for="(item, i) in equipmentWorkingList" :key="item.id">
                                        <li class="flex items-center gap-2 px-2 py-1.5 rounded border border-gray-200 bg-white"
                                            :data-id="item.id">
                                            <span class="cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-700 eq-handle">
                                                <i class="ri-draggable text-lg"></i>
                                            </span>
                                            <input type="checkbox" x-model="item.checked"
                                                class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                                            <span class="text-sm text-gray-800 flex-1 truncate"
                                                :class="item.checked ? '' : 'line-through text-gray-400'"
                                                x-text="item.label"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                                    {{ __('Add more') }}
                                </p>
                                <textarea x-model="equipmentPasteBuffer" @input="promoteBufferLines()" rows="4"
                                    placeholder="Bathing platform&#10;Bimini top&#10;Bow rail"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 font-mono"></textarea>
                                <p class="text-xs text-gray-500 mt-1">{{ __('Press Enter after each item to add it to the list above.') }}</p>
                            </div>
                        </div>

                        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-2">
                            <button type="button" @click="closeEquipmentModal()"
                                class="px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                                {{ __('Cancel') }}
                            </button>
                            <button type="button" @click="confirmEquipmentPaste()"
                                class="inline-flex items-center gap-1 px-4 py-1.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                <i class="ri-check-line"></i> {{ __('OK') }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Options tab --}}
            <section x-show="tab === 'options'" x-cloak class="space-y-4">
                {{-- Custom options repeater. (Importing from a file is offered
                     once the boat is saved, in the editor's Options tab.) --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ __('Custom options') }}</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ __('Anything not in the library — add your own.') }}</p>

                    <template x-for="(o, i) in newOptions" :key="i">
                        <div class="border border-gray-200 rounded-lg p-3 mb-2 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div class="md:col-span-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Category') }} *</label>
                                <input type="text" :name="`new_options[${i}][category]`" x-model="o.category"
                                    placeholder="{{ __('e.g. Electronics') }}"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Label') }} *</label>
                                <input type="text" :name="`new_options[${i}][label]`" x-model="o.label"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Public HT') }} *</label>
                                <input type="number" step="0.01" min="0" :name="`new_options[${i}][price]`" x-model="o.price"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Cost') }}</label>
                                <input type="number" step="0.01" min="0" :name="`new_options[${i}][cost]`" x-model="o.cost"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-1">
                                <button type="button" @click="newOptions.splice(i, 1)"
                                    class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="addOption()"
                        class="text-sm font-medium text-primary-800 hover:underline mt-2">
                        <i class="ri-add-line"></i> {{ __('Add custom option') }}
                    </button>
                </div>
            </section>

            {{-- Single global Save button --}}
            <div class="mt-4 flex items-center justify-end gap-2">
                <a href="{{ route('catalogue.models') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
                <button type="submit" :disabled="! boatName.trim()"
                    :title="! boatName.trim() ? '{{ __('Enter a boat name first') }}' : ''"
                    class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="ri-save-line"></i> {{ __('Create boat') }}
                </button>
            </div>
        </div>
    </form>

@else
    {{-- ════════════════════════════════════════════════════════════════
         EDIT MODE: each tab has its own per-row forms (one per variant /
         option). Faster for tweaking a single price without re-saving
         the whole boat.
         ════════════════════════════════════════════════════════════════ --}}
    <div x-data="{ tab: (new URLSearchParams(window.location.search)).get('tab') || 'boat', importOpen: false }">
        <div class="mb-4 border-b border-gray-200 flex items-center gap-1 overflow-x-auto">
            <button type="button" @click="tab = 'boat'"
                :class="tab === 'boat' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-sailboat-line"></i> {{ __('Boat') }}
            </button>
            <button type="button" @click="tab = 'versions'"
                :class="tab === 'versions' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-list-check-2"></i> {{ __('Versions') }} <span class="text-xs text-gray-400">({{ $variants->count() }})</span>
            </button>
            <button type="button" @click="tab = 'options'"
                :class="tab === 'options' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-add-circle-line"></i> {{ __('Options') }} <span class="text-xs text-gray-400">({{ $options->count() }})</span>
            </button>
        </div>

        {{-- ════════ ONE form: saving persists boat + versions + options ════════ --}}
        <form method="POST" action="{{ route('catalogue.models.save-all', $model->_id) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="active_tab" :value="tab" />

        {{-- Boat tab --}}
        <section x-show="tab === 'boat'" class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                @include('catalogue.partials._boat-fields', ['model' => $model, 'brands' => $brands])
            </div>
        </section>

        {{-- Versions tab — one form, bulk "Save versions" --}}
        @php
            $initialVersions = $variants->map(fn ($v) => [
                'id'         => (string) $v->_id,
                'name'       => $v->name,
                'base_price' => (float) $v->base_price,
                'cost'       => (float) ($v->cost ?? 0),
                'currency'   => $v->currency ?? 'EUR',
                'equipment'  => collect($v->included_equipment ?? [])
                    ->map(fn ($e) => is_array($e) ? ($e['label'] ?? '') : (string) $e)
                    ->filter()->values()->all(),
            ])->values();
        @endphp
        <section x-show="tab === 'versions'" x-cloak class="space-y-4"
            x-data="versionsBulk(@js($initialVersions))">
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">{{ __('Versions') }}</h2>
                <p class="text-xs text-gray-500 mb-4">{{ __('Add the versions you sell and edit them inline. Everything is saved together with the Save button at the bottom.') }}</p>

                    <template x-for="(v, i) in versions" :key="i">
                        <div class="border border-gray-200 rounded-lg p-3 mb-3">
                            <input type="hidden" :name="`versions[${i}][id]`" :value="v.id" />
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-5">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Version name') }} *</label>
                                    <input type="text" :name="`versions[${i}][name]`" x-model="v.name" required
                                        placeholder="e.g. 2x 200HP"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Public HT') }} *</label>
                                    <input type="number" step="0.01" min="0" :name="`versions[${i}][base_price]`" x-model="v.base_price" required
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Cost') }}</label>
                                    <input type="number" step="0.01" min="0" :name="`versions[${i}][cost]`" x-model="v.cost"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                                    <select :name="`versions[${i}][currency]`" x-model="v.currency"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                        <option value="EUR">EUR</option>
                                        <option value="USD">USD</option>
                                    </select>
                                </div>
                                <div class="md:col-span-1">
                                    <button type="button" @click="removeVersion(i)"
                                        class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg"
                                        title="{{ __('Remove') }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Equipment chips + add (hidden inputs post with the form) --}}
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        {{ __('Included equipment') }}
                                        <span class="text-gray-400 normal-case font-normal">(<span x-text="v.equipment.length"></span>)</span>
                                    </p>
                                    <button type="button" @click="openModal(i)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-primary-50 hover:bg-primary-100 text-primary-800 rounded-lg">
                                        <i class="ri-add-line"></i> {{ __('Add equipment') }}
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="(eq, ei) in v.equipment" :key="ei + '-' + eq">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs border border-emerald-200">
                                            <i class="ri-check-line text-emerald-600"></i>
                                            <span x-text="eq"></span>
                                            <input type="hidden" :name="`versions[${i}][equipment][]`" :value="eq" />
                                            <button type="button" @click="v.equipment.splice(ei, 1)"
                                                class="opacity-50 hover:opacity-100 text-emerald-700 hover:text-red-600 ml-1">
                                                <i class="ri-close-line"></i>
                                            </button>
                                        </span>
                                    </template>
                                    <template x-if="v.equipment.length === 0">
                                        <span class="text-xs text-gray-400 italic">{{ __('No equipment yet.') }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="versions.length === 0">
                        <p class="text-sm text-gray-500 italic mb-3">{{ __('No versions yet — add the first one below.') }}</p>
                    </template>

                    <button type="button" @click="addVersion()"
                        class="text-sm font-medium text-primary-800 hover:underline mt-1">
                        <i class="ri-add-line"></i> {{ __('Add version') }}
                    </button>
            </div>

            {{-- Shared equipment modal — single instance per section. Shows
                 the current items pre-checked + sortable, plus a paste box
                 for new entries. Commit overwrites the target's list with
                 the modal's working copy so reorders + removals are saved
                 together. --}}
            <div x-show="modalOpen" x-cloak x-transition.opacity
                @keydown.escape.window="closeModal()"
                class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
                <div @click.outside="closeModal()"
                    class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                            <i class="ri-tools-line"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900">{{ __('Included equipment') }}</h3>
                            <p class="text-xs text-gray-500">{{ __('Drag to reorder · untick to remove · paste below to add more.') }}</p>
                        </div>
                        <button type="button" @click="closeModal()"
                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>

                    <div class="p-5 space-y-4 overflow-y-auto">
                        {{-- Existing items, sortable --}}
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                                {{ __('Current items') }} <span class="text-gray-400 font-normal normal-case">(<span x-text="workingList.length"></span>)</span>
                            </p>
                            <template x-if="workingList.length === 0">
                                <p class="text-xs text-gray-400 italic">{{ __('No items yet — paste some below.') }}</p>
                            </template>
                            <ul x-ref="sortableList" class="space-y-1.5">
                                <template x-for="(item, i) in workingList" :key="item.id">
                                    <li class="flex items-center gap-2 px-2 py-1.5 rounded border border-gray-200 bg-white"
                                        :data-id="item.id">
                                        <span class="cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-700 eq-handle">
                                            <i class="ri-draggable text-lg"></i>
                                        </span>
                                        <input type="checkbox" x-model="item.checked"
                                            class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                                        <span class="text-sm text-gray-800 flex-1 truncate"
                                            :class="item.checked ? '' : 'line-through text-gray-400'"
                                            x-text="item.label"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Paste box for new items --}}
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                                {{ __('Add more') }}
                            </p>
                            <textarea x-model="pasteBuffer" @input="promoteBufferLines()" rows="4"
                                placeholder="Bathing platform&#10;Bimini top&#10;Bow rail"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 font-mono"></textarea>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Press Enter after each item to add it to the list above.') }}</p>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-2">
                        <button type="button" @click="closeModal()"
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                            {{ __('Cancel') }}
                        </button>
                        <button type="button" @click="commit()"
                            class="inline-flex items-center gap-1 px-4 py-1.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                            <i class="ri-check-line"></i> {{ __('OK') }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- Options tab --}}
        <section x-show="tab === 'options'" x-cloak class="space-y-4">

            @php $importResult = session('import_result'); @endphp
            @if ($importResult && ! empty($importResult['errors']))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-medium mb-2">
                        {{ __(':count row(s) could not be imported', ['count' => count($importResult['errors'])]) }}:
                    </p>
                    <ul class="list-disc list-inside space-y-0.5 text-xs">
                        @foreach (array_slice($importResult['errors'], 0, 20) as $err)
                            <li>{{ __('Row') }} {{ $err['row'] }} — {{ $err['message'] }}</li>
                        @endforeach
                        @if (count($importResult['errors']) > 20)
                            <li class="italic">{{ __('…and :count more', ['count' => count($importResult['errors']) - 20]) }}</li>
                        @endif
                    </ul>
                </div>
            @endif

            <div class="flex items-center justify-end">
                <button type="button" @click="importOpen = true"
                    class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg">
                    <i class="ri-upload-2-line"></i> {{ __('Import options from file') }}
                </button>
            </div>

            {{-- Import-options modal (scoped to this boat).
                 Teleported to <body> so it escapes any ancestor with a
                 transform / filter — otherwise position:fixed gets re-
                 anchored to that ancestor (Chromium behaviour) and the
                 overlay only covers part of the viewport. --}}
            <template x-teleport="body">
            <div x-show="importOpen" x-cloak x-transition.opacity
                @keydown.escape.window="importOpen = false"
                class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
                <div @click.outside="importOpen = false"
                    class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                            <i class="ri-upload-2-line"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900">{{ __('Import options for this boat') }}</h3>
                            <p class="text-xs text-gray-500 truncate">{{ $model->name }}</p>
                        </div>
                        <button type="button" @click="importOpen = false"
                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="rounded-lg border border-primary-200 bg-primary-50/40 px-4 py-3">
                            <p class="text-sm font-semibold text-primary-900 mb-1">
                                <i class="ri-information-line"></i> {{ __('Need a template?') }}
                            </p>
                            <p class="text-xs text-gray-700 mb-2">
                                {{ __('Download the sample file, fill in your options, then upload it back here.') }}
                            </p>
                            <a href="{{ route('catalogue.options.template-for-boat', $model->_id) }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-white border border-primary-200 hover:bg-primary-50 text-primary-800 rounded-lg">
                                <i class="ri-download-2-line"></i> {{ __('Download template (XLSX)') }}
                            </a>
                        </div>

                        <form method="POST" action="{{ route('catalogue.options.import-for-boat', $model->_id) }}"
                            enctype="multipart/form-data"
                            class="space-y-3"
                            x-data="{ filename: '', submitting: false, helpOpen: false }"
                            @submit="submitting = true">
                            @csrf
                            <div>
                                <div class="flex items-center gap-1.5 mb-1">
                                    <label class="block text-sm font-medium text-gray-700">{{ __('File') }} <span class="text-red-500">*</span></label>
                                    <div class="relative" @mouseleave="helpOpen = false">
                                        <button type="button"
                                            @mouseenter="helpOpen = true"
                                            @click="helpOpen = !helpOpen"
                                            class="w-4 h-4 inline-flex items-center justify-center rounded-full bg-gray-200 hover:bg-primary-100 text-gray-600 hover:text-primary-800 text-[10px] font-bold transition"
                                            aria-label="{{ __('What columns does the file need?') }}">
                                            ?
                                        </button>
                                        <div x-show="helpOpen" x-cloak x-transition.opacity
                                            class="absolute left-0 top-6 z-10 w-80 bg-gray-900 text-white rounded-lg shadow-xl p-3 text-xs">
                                            <p class="font-semibold mb-1.5">{{ __('Expected columns') }}</p>
                                            <ul class="space-y-0.5 text-white/90">
                                                <li><span class="font-mono text-primary-200">FAMILLE</span> — {{ __('required, category') }}</li>
                                                <li><span class="font-mono text-primary-200">DESIGNATION</span> — {{ __('required, option name') }}</li>
                                                <li><span class="font-mono text-primary-200">PA HT</span> — {{ __('optional, purchase cost') }}</li>
                                                <li><span class="font-mono text-primary-200">PA CURRENCY</span> — {{ __('optional, EUR or USD (defaults EUR)') }}</li>
                                                <li><span class="font-mono text-primary-200">PV HT</span> — {{ __('required, selling price HT') }}</li>
                                                <li><span class="font-mono text-primary-200">PV CURRENCY</span> — {{ __('optional, EUR or USD (defaults EUR)') }}</li>
                                                <li><span class="font-mono text-primary-200">TVA</span> — {{ __('optional, defaults to 20') }}</li>
                                            </ul>
                                            <p class="text-white/70 mt-2 pt-2 border-t border-white/10">
                                                {{ __('USD amounts are converted to EUR at the current ECB rate. Re-uploading the same file with new prices updates existing options; renaming an option creates a new one.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <input type="file" name="file" required
                                    accept=".csv,.xlsx,.xlsm,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                                    @change="filename = $event.target.files[0]?.name || ''"
                                    class="w-full text-sm file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-primary-50 file:text-primary-800 file:font-semibold hover:file:bg-primary-100" />
                                <p class="text-xs text-gray-500 mt-1">{{ __('CSV or XLSX. Max 10 MB. Up to 5000 rows.') }}</p>
                                <x-input-error :messages="$errors->get('file')" class="mt-1" />
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2">
                                <button type="button" @click="importOpen = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" :disabled="submitting"
                                    class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50">
                                    <span x-show="!submitting"><i class="ri-upload-2-line"></i> {{ __('Import') }}</span>
                                    <span x-show="submitting" x-cloak><i class="ri-loader-4-line animate-spin"></i> {{ __('Importing…') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            </template>

            {{-- Per-boat options list — one form, bulk "Save options" --}}
            @php
                $initialOptions = $options->map(fn ($o) => [
                    'id'       => (string) $o->_id,
                    'category' => $o->category,
                    'label'    => $o->label,
                    'price'    => (float) $o->price,
                    'cost'     => (float) ($o->cost ?? 0),
                    'currency' => $o->currency ?? 'EUR',
                ])->values();
            @endphp
            <div class="bg-white rounded-2xl border border-gray-200 p-6" x-data="optionsBulk(@js($initialOptions))">
                <h2 class="text-base font-semibold text-gray-900 mb-1">{{ __('Options on this boat') }}</h2>
                <p class="text-xs text-gray-500 mb-4">{{ __('Add options and set prices. USD amounts convert to EUR on save. Everything is saved together with the Save button at the bottom.') }}</p>

                    <template x-for="(o, i) in options" :key="i">
                        <div class="border border-gray-200 rounded-lg p-3 mb-2 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <input type="hidden" :name="`options[${i}][id]`" :value="o.id" />
                            <div class="md:col-span-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Category') }} *</label>
                                <input type="text" :name="`options[${i}][category]`" x-model="o.category" required placeholder="{{ __('e.g. Electronics') }}"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Label') }} *</label>
                                <input type="text" :name="`options[${i}][label]`" x-model="o.label" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Public HT') }} *</label>
                                <input type="number" step="0.01" min="0" :name="`options[${i}][price]`" x-model="o.price" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Cost') }}</label>
                                <input type="number" step="0.01" min="0" :name="`options[${i}][cost]`" x-model="o.cost"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                                <select :name="`options[${i}][currency]`" x-model="o.currency"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                            <div class="md:col-span-1">
                                <button type="button" @click="removeOption(i)"
                                    class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg"
                                    title="{{ __('Remove') }}">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-if="options.length === 0">
                        <p class="text-sm text-gray-500 italic mb-3">{{ __('No options yet — import a file or add a custom one below.') }}</p>
                    </template>

                    <button type="button" @click="addOption()"
                        class="text-sm font-medium text-primary-800 hover:underline mt-1">
                        <i class="ri-add-line"></i> {{ __('Add custom option') }}
                    </button>
            </div>
        </section>

        {{-- Single Save for the whole boat (boat fields + versions + options). --}}
        <div class="mt-5 flex items-center justify-end gap-2">
            <a href="{{ route('catalogue.models') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button type="submit" class="inline-flex items-center gap-1 px-5 py-2.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg shadow">
                <i class="ri-save-line"></i> {{ __('Save') }}
            </button>
        </div>
        </form>

        {{-- Delete — kept outside the save form (private boats only). --}}
        @if ($model->source === 'private')
            <section x-show="tab === 'boat'" x-cloak class="mt-4">
                <div class="bg-white rounded-2xl border border-red-200 p-5">
                    <p class="text-sm text-gray-600 mb-3">{{ __('Delete this boat and all its versions/options. Existing quotes are preserved (snapshots).') }}</p>
                    <form method="POST" action="{{ route('catalogue.models.destroy', $model->_id) }}"
                        data-confirm="{{ __('Permanently delete «:name» and all its versions and options? This cannot be undone.', ['name' => $model->name]) }}"
                        data-confirm-danger="1">
                        @csrf @method('DELETE')
                        <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg">
                            <i class="ri-delete-bin-line"></i> {{ __('Delete boat') }}
                        </button>
                    </form>
                </div>
            </section>
        @endif
    </div>
@endif

    @push('scripts')
    <script>
        // Brand typeahead — used in both create + edit modes by the Boat fields partial.
        function brandTypeahead(initialId, initialName) {
            return {
                selectedId: initialId || '',
                query: initialName || '',
                results: [],
                cursor: -1,
                open: false,
                adding: false,
                csrf: document.querySelector('meta[name="csrf-token"]')?.content,

                async init() {
                    if (this.selectedId && this.query) return;
                    await this.search();
                },
                async search() {
                    this.open = true;
                    this.cursor = -1;
                    let url = '{{ route('catalogue.brands.lookup') }}?q=' + encodeURIComponent(this.query);
                    let res = await fetch(url, { headers: { 'Accept': 'application/json' }});
                    this.results = await res.json();
                },
                select(item) {
                    this.selectedId = item.id;
                    this.query = item.name;
                    this.open = false;
                },
                async confirmFreeText() {
                    if (this.query.trim().length === 0) return;
                    await this.quickAdd();
                },
                async quickAdd() {
                    if (this.adding) return;
                    this.adding = true;
                    try {
                        let res = await fetch('{{ route('catalogue.brands.inline') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            body: JSON.stringify({ name: this.query.trim() }),
                        });
                        if (!res.ok) throw new Error('Failed');
                        let item = await res.json();
                        this.select(item);
                    } catch (e) {
                        alert('{{ __('Could not add the brand. Try again or refresh the page.') }}');
                    } finally {
                        this.adding = false;
                    }
                },
            };
        }

        // Repeater state for the create-mode page (versions + custom options).
        // Each version owns its own `equipment` array — managed via the
        // equipment modal that shows current items (pre-checked, sortable)
        // plus a paste box for new ones.
        function boatCreator() {
            // Auto-increment id generator for working-list items so
            // SortableJS can match :key bindings stably.
            let eqIdCounter = 1;
            const nextEqId = () => `e${eqIdCounter++}`;

            return {
                // Tracks the boat-name field so the "Create boat" button can
                // stay disabled until a name is entered. Seeded from old()
                // so it's correct after a validation redirect.
                boatName: @js(old('name', '')),

                // Start with no versions — a boat can be created with just a
                // name; versions (and options) are optional and can be added
                // later from the editor.
                versions: [],
                newOptions: [],

                // Equipment modal state — single instance shared across rows.
                equipmentModalOpen: false,
                modalIndex: null,
                equipmentPasteBuffer: '',
                equipmentWorkingList: [],   // [{id, label, checked}]
                equipmentSortable: null,

                addVersion() {
                    this.versions.push({ name: '', base_price: '', cost: '', currency: 'EUR', equipment: [] });
                },
                addOption() {
                    this.newOptions.push({ category: '', label: '', price: '', cost: '' });
                },

                async openEquipmentModal(i) {
                    this.modalIndex = i;
                    this.equipmentPasteBuffer = '';
                    // Seed the working list from the version's current equipment.
                    this.equipmentWorkingList = (this.versions[i].equipment || []).map(label => ({
                        id: nextEqId(),
                        label,
                        checked: true,
                    }));
                    this.equipmentModalOpen = true;
                    // Wait for DOM to render then attach SortableJS.
                    await this.$nextTick();
                    await this.ensureSortable();
                    if (this.equipmentSortable) this.equipmentSortable.destroy();
                    this.equipmentSortable = window.Sortable.create(this.$refs.equipmentSortableList, {
                        handle: '.eq-handle',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: (evt) => this.applySortableOrder(),
                    });
                },
                closeEquipmentModal() {
                    if (this.equipmentSortable) { this.equipmentSortable.destroy(); this.equipmentSortable = null; }
                    this.equipmentModalOpen = false;
                    this.modalIndex = null;
                    this.equipmentPasteBuffer = '';
                    this.equipmentWorkingList = [];
                },
                ensureSortable() {
                    if (window.Sortable) return Promise.resolve();
                    return new Promise((resolve, reject) => {
                        const s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
                        s.onload = resolve;
                        s.onerror = reject;
                        document.head.appendChild(s);
                    });
                },
                applySortableOrder() {
                    // Read DOM order via data-id and re-shuffle the working list.
                    const ids = Array.from(this.$refs.equipmentSortableList.querySelectorAll('[data-id]')).map(el => el.dataset.id);
                    const byId = Object.fromEntries(this.equipmentWorkingList.map(r => [r.id, r]));
                    this.equipmentWorkingList = ids.map(id => byId[id]).filter(Boolean);
                },
                /**
                 * Called on every keystroke in the paste textarea. Any line
                 * that's terminated by a newline gets promoted to a ticked
                 * checkbox in the working list immediately; the unfinished
                 * tail stays in the buffer so the user can keep typing.
                 * Deduped case-insensitively against the existing list.
                 */
                promoteBufferLines() {
                    const buf = this.equipmentPasteBuffer;
                    const lastNl = Math.max(buf.lastIndexOf('\n'), buf.lastIndexOf('\r'));
                    if (lastNl < 0) return; // no completed line yet
                    const completed = buf.substring(0, lastNl);
                    const tail      = buf.substring(lastNl + 1);

                    const existing = new Set(this.equipmentWorkingList.map(r => r.label.toLowerCase()));
                    for (const raw of completed.split(/\r?\n/)) {
                        const line = raw.trim();
                        if (! line) continue;
                        if (existing.has(line.toLowerCase())) continue;
                        this.equipmentWorkingList.push({ id: nextEqId(), label: line, checked: true });
                        existing.add(line.toLowerCase());
                    }
                    this.equipmentPasteBuffer = tail;
                },
                confirmEquipmentPaste() {
                    if (this.modalIndex === null) return;
                    // Flush any trailing line still in the buffer (user
                    // didn't hit Enter before clicking OK).
                    if (this.equipmentPasteBuffer.trim()) {
                        this.equipmentPasteBuffer += '\n';
                        this.promoteBufferLines();
                    }
                    // Read DOM order one more time in case the user dragged.
                    this.applySortableOrder();

                    const kept = this.equipmentWorkingList
                        .filter(item => item.checked)
                        .map(item => item.label);

                    this.versions[this.modalIndex].equipment = kept;
                    this.closeEquipmentModal();
                },
            };
        }

        // Edit-mode Versions tab — one Alpine array + a single "Save versions"
        // form. Reuses the shared equipment paste modal (markup in the section).
        function versionsBulk(initial) {
            let eqId = 1;
            const nextEqId = () => `e${eqId++}`;

            return {
                versions: (initial || []).map(v => ({
                    id: v.id || '',
                    name: v.name ?? '',
                    base_price: v.base_price ?? '',
                    cost: v.cost ?? '',
                    currency: v.currency || 'EUR',
                    equipment: Array.isArray(v.equipment) ? v.equipment : [],
                })),

                // Shared equipment modal state.
                modalOpen: false,
                modalIndex: null,
                pasteBuffer: '',
                workingList: [],   // [{id,label,checked}]
                sortable: null,

                addVersion() {
                    this.versions.push({ id: '', name: '', base_price: '', cost: '', currency: 'EUR', equipment: [] });
                },
                removeVersion(i) {
                    this.versions.splice(i, 1);
                },

                async openModal(i) {
                    this.modalIndex = i;
                    this.pasteBuffer = '';
                    this.workingList = (this.versions[i].equipment || []).map(label => ({ id: nextEqId(), label, checked: true }));
                    this.modalOpen = true;
                    await this.$nextTick();
                    await this.ensureSortable();
                    if (this.sortable) this.sortable.destroy();
                    this.sortable = window.Sortable.create(this.$refs.sortableList, {
                        handle: '.eq-handle', animation: 150, ghostClass: 'opacity-50',
                        onEnd: () => this.applyOrder(),
                    });
                },
                closeModal() {
                    if (this.sortable) { this.sortable.destroy(); this.sortable = null; }
                    this.modalOpen = false; this.modalIndex = null; this.pasteBuffer = ''; this.workingList = [];
                },
                ensureSortable() {
                    if (window.Sortable) return Promise.resolve();
                    return new Promise((resolve, reject) => {
                        const s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
                        s.onload = resolve; s.onerror = reject;
                        document.head.appendChild(s);
                    });
                },
                applyOrder() {
                    const ids = Array.from(this.$refs.sortableList.querySelectorAll('[data-id]')).map(el => el.dataset.id);
                    const byId = Object.fromEntries(this.workingList.map(r => [r.id, r]));
                    this.workingList = ids.map(id => byId[id]).filter(Boolean);
                },
                promoteBufferLines() {
                    const buf = this.pasteBuffer;
                    const lastNl = Math.max(buf.lastIndexOf('\n'), buf.lastIndexOf('\r'));
                    if (lastNl < 0) return;
                    const completed = buf.substring(0, lastNl);
                    const tail      = buf.substring(lastNl + 1);
                    const existing = new Set(this.workingList.map(r => r.label.toLowerCase()));
                    for (const raw of completed.split(/\r?\n/)) {
                        const line = raw.trim();
                        if (! line || existing.has(line.toLowerCase())) continue;
                        this.workingList.push({ id: nextEqId(), label: line, checked: true });
                        existing.add(line.toLowerCase());
                    }
                    this.pasteBuffer = tail;
                },
                commit() {
                    if (this.modalIndex === null) { this.closeModal(); return; }
                    if (this.pasteBuffer.trim()) { this.pasteBuffer += '\n'; this.promoteBufferLines(); }
                    this.applyOrder();
                    const kept = this.workingList.filter(i => i.checked).map(i => i.label);
                    this.versions[this.modalIndex].equipment = kept;
                    this.closeModal();
                },
            };
        }

        // Edit-mode Options tab — one Alpine array + a single "Save options" form.
        function optionsBulk(initial) {
            return {
                options: (initial || []).map(o => ({
                    id: o.id || '',
                    category: o.category ?? '',
                    label: o.label ?? '',
                    price: o.price ?? '',
                    cost: o.cost ?? '',
                    currency: o.currency || 'EUR',
                })),
                addOption() {
                    this.options.push({ id: '', category: '', label: '', price: '', cost: '', currency: 'EUR' });
                },
                removeOption(i) {
                    this.options.splice(i, 1);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
