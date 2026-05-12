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
            <i class="ri-arrow-left-line"></i> Back to catalogue
        </a>
        @if (! $isNew)
            <span>·</span>
            <span class="font-mono px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">{{ $model->code }}</span>
            @if ($model->source === 'private')
                <span class="px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 text-[11px] font-semibold">Private</span>
            @endif
        @endif
    </div>

@if ($isNew)
    {{-- ════════════════════════════════════════════════════════════════
         CREATE MODE: one form wraps everything. Single Save persists
         boat + versions + options (custom & library) + equipment.
         ════════════════════════════════════════════════════════════════ --}}
    <form method="POST" action="{{ route('catalogue.models.store') }}"
          x-data="boatCreator()" class="space-y-4">
        @csrf

        <div x-data="{ tab: 'boat' }">
            {{-- Tabs --}}
            <div class="mb-4 border-b border-gray-200 flex items-center gap-1 overflow-x-auto">
                <button type="button" @click="tab = 'boat'"
                    :class="tab === 'boat' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                    <i class="ri-sailboat-line"></i> Boat
                </button>
                <button type="button" @click="tab = 'versions'"
                    :class="tab === 'versions' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                    <i class="ri-list-check-2"></i> Versions
                    <span class="text-xs text-gray-400">(<span x-text="versions.length"></span>)</span>
                </button>
                <button type="button" @click="tab = 'options'"
                    :class="tab === 'options' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                    <i class="ri-add-circle-line"></i> Options
                </button>
            </div>

            {{-- Boat tab --}}
            <section x-show="tab === 'boat'" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                @include('catalogue.partials._boat-fields', ['model' => $model, 'brands' => $brands])
            </section>

            {{-- Versions tab — Alpine repeater --}}
            <section x-show="tab === 'versions'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Versions</h2>
                <p class="text-xs text-gray-500 mb-4">A version is a specific configuration (e.g. "2× 200HP"). Add at least one so you can sell this boat.</p>

                <template x-for="(v, i) in versions" :key="i">
                    <div class="border border-gray-200 rounded-lg p-3 mb-3">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div class="md:col-span-5">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Version name') }} *</label>
                                <input type="text" :name="`versions[${i}][name]`" x-model="v.name"
                                    placeholder="e.g. 2x 200HP" required
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

                {{-- Paste-equipment modal — single instance, targets versions[modalIndex] --}}
                <div x-show="equipmentModalOpen" x-cloak x-transition.opacity
                    @keydown.escape.window="closeEquipmentModal()"
                    class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
                    <div @click.outside="closeEquipmentModal()"
                        class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                                <i class="ri-clipboard-line"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900">{{ __('Paste a list of equipment') }}</h3>
                                <p class="text-xs text-gray-500">{{ __('One item per line. Each line becomes a ticked checkbox below.') }}</p>
                            </div>
                            <button type="button" @click="closeEquipmentModal()"
                                class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg">
                                <i class="ri-close-line text-xl"></i>
                            </button>
                        </div>
                        <div class="p-5 space-y-3">
                            <textarea x-model="equipmentPasteBuffer" rows="6"
                                placeholder="Bathing platform&#10;Bimini top&#10;Bow rail"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 font-mono"></textarea>

                            <template x-if="equipmentPreview.length > 0">
                                <div class="rounded-lg border border-gray-200 p-3 bg-gray-50">
                                    <p class="text-xs font-semibold text-gray-600 mb-2">
                                        {{ __('Preview') }} <span class="text-gray-400 font-normal">(<span x-text="equipmentPreview.length"></span>)</span>
                                    </p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-60 overflow-y-auto">
                                        <template x-for="(label, pi) in equipmentPreview" :key="pi">
                                            <label class="flex items-center gap-2 px-2 py-1.5 rounded bg-white border border-gray-200 text-xs">
                                                <input type="checkbox" checked disabled
                                                    class="rounded border-gray-300 text-primary-800" />
                                                <span class="text-gray-800 truncate" x-text="label"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-500">{{ __('One item per line. Click OK when done.') }}</span>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="closeEquipmentModal()"
                                    class="px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="button" @click="confirmEquipmentPaste()"
                                    :disabled="equipmentPreview.length === 0 && equipmentPasteBuffer.trim().length === 0"
                                    class="inline-flex items-center gap-1 px-4 py-1.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50">
                                    <i class="ri-check-line"></i> {{ __('OK') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Options tab --}}
            <section x-show="tab === 'options'" x-cloak class="space-y-4">
                {{-- Library picker (checkboxes -> library_option_ids[]) --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Pick from library</h3>
                    <p class="text-xs text-gray-500 mb-3">Tick options to add to this boat. You can adjust prices later.</p>
                    <div class="max-h-72 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                        @foreach ($libraryOptions->groupBy('category') as $category => $items)
                            <div>
                                <div class="px-3 py-2 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">{{ $category }}</div>
                                @foreach ($items as $opt)
                                    <label class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <input type="checkbox" name="library_option_ids[]" value="{{ $opt->_id }}"
                                                class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                                            <span class="text-sm text-gray-900 truncate">{{ $opt->label }}</span>
                                        </div>
                                        <span class="text-sm text-gray-700 font-semibold whitespace-nowrap">€{{ number_format($opt->price, 0, ',', ' ') }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Custom options repeater --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Custom options</h3>
                    <p class="text-xs text-gray-500 mb-3">Anything not in the library — add your own.</p>

                    <template x-for="(o, i) in newOptions" :key="i">
                        <div class="border border-gray-200 rounded-lg p-3 mb-2 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div class="md:col-span-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Category *</label>
                                <input type="text" :name="`new_options[${i}][category]`" x-model="o.category" required
                                    placeholder="e.g. Electronics"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Label *</label>
                                <input type="text" :name="`new_options[${i}][label]`" x-model="o.label" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Public HT *</label>
                                <input type="number" step="0.01" min="0" :name="`new_options[${i}][price]`" x-model="o.price" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
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
                        <i class="ri-add-line"></i> Add custom option
                    </button>
                </div>
            </section>

            {{-- Single global Save button --}}
            <div class="mt-4 flex items-center justify-end gap-2">
                <a href="{{ route('catalogue.models') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</a>
                <button type="submit"
                    class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                    <i class="ri-save-line"></i> Create boat
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
    <div x-data="{ tab: 'boat' }">
        <div class="mb-4 border-b border-gray-200 flex items-center gap-1 overflow-x-auto">
            <button type="button" @click="tab = 'boat'"
                :class="tab === 'boat' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-sailboat-line"></i> Boat
            </button>
            <button type="button" @click="tab = 'versions'"
                :class="tab === 'versions' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-list-check-2"></i> Versions <span class="text-xs text-gray-400">({{ $variants->count() }})</span>
            </button>
            <button type="button" @click="tab = 'options'"
                :class="tab === 'options' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-add-circle-line"></i> Options <span class="text-xs text-gray-400">({{ $options->count() }})</span>
            </button>
        </div>

        {{-- Boat tab --}}
        <section x-show="tab === 'boat'" class="space-y-4">
            <form method="POST" action="{{ route('catalogue.models.update', $model->_id) }}"
                class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                @csrf @method('PATCH')

                @include('catalogue.partials._boat-fields', ['model' => $model, 'brands' => $brands])

                <div class="flex justify-end pt-2 border-t border-gray-100">
                    <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                        <i class="ri-save-line"></i> Save boat
                    </button>
                </div>
            </form>

            @if ($model->source === 'private')
                <div class="bg-white rounded-2xl border border-red-200 p-5">
                    <p class="text-sm text-gray-600 mb-3">Delete this boat and all its versions/options. Existing quotes are preserved (snapshots).</p>
                    <form method="POST" action="{{ route('catalogue.models.destroy', $model->_id) }}"
                        onsubmit="return confirm('Delete «{{ $model->name }}»?');">
                        @csrf @method('DELETE')
                        <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg">
                            <i class="ri-delete-bin-line"></i> Delete boat
                        </button>
                    </form>
                </div>
            @endif
        </section>

        {{-- Versions tab --}}
        <section x-show="tab === 'versions'" x-cloak class="space-y-4"
            x-data="variantsEditor()">
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('Versions') }}</h2>
                @if ($variants->isEmpty())
                    <p class="text-sm text-gray-500 italic mb-4">{{ __('No versions yet — add the first one below.') }}</p>
                @else
                    <div class="space-y-4 mb-6">
                        @foreach ($variants as $v)
                            @php
                                $initialEquipment = collect($v->included_equipment ?? [])
                                    ->map(fn ($e) => is_array($e) ? ($e['label'] ?? '') : (string) $e)
                                    ->filter()
                                    ->values()
                                    ->all();
                            @endphp
                            <div class="border border-gray-200 rounded-lg p-3"
                                x-data="{ equipment: @js($initialEquipment) }"
                                x-init="register($el, equipment)">
                                <form method="POST" action="{{ route('catalogue.variants.update', $v->_id) }}"
                                    class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                    @csrf @method('PATCH')
                                    <div class="md:col-span-5">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Version name') }}</label>
                                        <input type="text" name="name" value="{{ $v->name }}" required
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Public HT') }}</label>
                                        <input type="number" step="0.01" min="0" name="base_price" value="{{ $v->base_price }}" required
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Cost') }}</label>
                                        <input type="number" step="0.01" min="0" name="cost" value="{{ $v->cost }}"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                                        <select name="currency" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                            <option value="EUR" @selected($v->currency === 'EUR')>EUR</option>
                                            <option value="USD" @selected($v->currency === 'USD')>USD</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-1 flex items-center gap-2 justify-end">
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-2 text-xs font-medium bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                            <i class="ri-save-line"></i>
                                        </button>
                                    </div>

                                    {{-- Equipment hidden inputs are inside the form so they post on Save --}}
                                    <template x-for="(eq, ei) in equipment" :key="ei + '-' + eq">
                                        <input type="hidden" name="equipment[]" :value="eq" />
                                    </template>
                                </form>

                                {{-- Equipment chips + add button — outside the form so the buttons don't submit it --}}
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            {{ __('Included equipment') }}
                                            <span class="text-gray-400 normal-case font-normal">(<span x-text="equipment.length"></span>)</span>
                                        </p>
                                        <button type="button" @click="openModalFor($data)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-primary-50 hover:bg-primary-100 text-primary-800 rounded-lg">
                                            <i class="ri-add-line"></i> {{ __('Add equipment') }}
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="(eq, ei) in equipment" :key="ei + '-' + eq">
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs border border-emerald-200">
                                                <i class="ri-check-line text-emerald-600"></i>
                                                <span x-text="eq"></span>
                                                <button type="button" @click="equipment.splice(ei, 1)"
                                                    class="opacity-50 hover:opacity-100 text-emerald-700 hover:text-red-600 ml-1">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            </span>
                                        </template>
                                        <template x-if="equipment.length === 0">
                                            <span class="text-xs text-gray-400 italic">{{ __('No equipment yet.') }}</span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('catalogue.variants.destroy', $v->_id) }}"
                                onsubmit="return confirm('{{ __('Remove') }} «{{ $v->name }}»?');" class="-mt-3 text-right">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline"><i class="ri-delete-bin-line"></i> {{ __('Remove') }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif

                <div x-data="{ open: false, newEquipment: [] }" class="border-t border-gray-100 pt-4">
                    <button type="button" @click="open = !open" class="text-sm font-medium text-primary-800 hover:underline">
                        <i class="ri-add-line"></i> {{ __('Add version') }}
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('catalogue.variants.store', $model->_id) }}"
                        class="mt-3 space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div class="md:col-span-5">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Name') }} *</label>
                                <input type="text" name="name" required placeholder="e.g. IDEA60 — 2x 200HP"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Public HT') }} *</label>
                                <input type="number" step="0.01" min="0" name="base_price" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Cost') }}</label>
                                <input type="number" step="0.01" min="0" name="cost"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                                <select name="currency" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                            <div class="md:col-span-1">
                                <button class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 text-xs font-medium bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                    <i class="ri-add-line"></i>
                                </button>
                            </div>
                        </div>

                        <template x-for="(eq, ei) in newEquipment" :key="ei + '-' + eq">
                            <input type="hidden" name="equipment[]" :value="eq" />
                        </template>

                        <div class="pt-2 border-t border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {{ __('Included equipment') }}
                                    <span class="text-gray-400 normal-case font-normal">(<span x-text="newEquipment.length"></span>)</span>
                                </p>
                                <button type="button" @click="openModalFor({ equipment: newEquipment })"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-primary-50 hover:bg-primary-100 text-primary-800 rounded-lg">
                                    <i class="ri-add-line"></i> {{ __('Add equipment') }}
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(eq, ei) in newEquipment" :key="ei + '-' + eq">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs border border-emerald-200">
                                        <i class="ri-check-line text-emerald-600"></i>
                                        <span x-text="eq"></span>
                                        <button type="button" @click="newEquipment.splice(ei, 1)"
                                            class="opacity-50 hover:opacity-100 text-emerald-700 hover:text-red-600 ml-1">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Shared paste modal — single instance for this section, targets
                 whichever variant row called openModalFor(). --}}
            <div x-show="modalOpen" x-cloak x-transition.opacity
                @keydown.escape.window="closeModal()"
                class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
                <div @click.outside="closeModal()"
                    class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                            <i class="ri-clipboard-line"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900">{{ __('Paste a list of equipment') }}</h3>
                            <p class="text-xs text-gray-500">{{ __('One item per line. Each line becomes a ticked checkbox below.') }}</p>
                        </div>
                        <button type="button" @click="closeModal()"
                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>
                    <div class="p-5 space-y-3">
                        <textarea x-model="pasteBuffer" rows="6"
                            placeholder="Bathing platform&#10;Bimini top&#10;Bow rail"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 font-mono"></textarea>

                        <template x-if="preview.length > 0">
                            <div class="rounded-lg border border-gray-200 p-3 bg-gray-50">
                                <p class="text-xs font-semibold text-gray-600 mb-2">
                                    {{ __('Preview') }} <span class="text-gray-400 font-normal">(<span x-text="preview.length"></span>)</span>
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-60 overflow-y-auto">
                                    <template x-for="(label, pi) in preview" :key="pi">
                                        <label class="flex items-center gap-2 px-2 py-1.5 rounded bg-white border border-gray-200 text-xs">
                                            <input type="checkbox" checked disabled
                                                class="rounded border-gray-300 text-primary-800" />
                                            <span class="text-gray-800 truncate" x-text="label"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-500">{{ __('One item per line. Click OK when done.') }}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="closeModal()"
                                class="px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                                {{ __('Cancel') }}
                            </button>
                            <button type="button" @click="commit()"
                                :disabled="preview.length === 0"
                                class="inline-flex items-center gap-1 px-4 py-1.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50">
                                <i class="ri-check-line"></i> {{ __('OK') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Options tab --}}
        <section x-show="tab === 'options'" x-cloak class="space-y-4">
            {{-- Per-boat options list --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Options on this boat</h2>
                @if ($options->isEmpty())
                    <p class="text-sm text-gray-500 italic mb-4">No options yet — pick from the library above or add a custom one below.</p>
                @else
                    <p class="text-xs text-gray-500 mb-3"><i class="ri-drag-move-2-line"></i> Drag the handle on the left to reorder. Order is saved automatically.</p>
                    <div
                        x-data="optionsSortable('{{ route('catalogue.options.reorder', $model->_id) }}')"
                        x-init="init($el)"
                        class="space-y-2 mb-6"
                        data-options-sortable>
                        @foreach ($options as $o)
                            <div class="opt-row" data-id="{{ $o->_id }}">
                                <form method="POST" action="{{ route('catalogue.options.update', $o->_id) }}"
                                    class="border border-gray-200 rounded-lg p-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                    @csrf @method('PATCH')
                                    <div class="md:col-span-1 flex md:items-end md:justify-center">
                                        <span class="opt-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-700 py-2 px-1" title="Drag to reorder">
                                            <i class="ri-draggable text-xl"></i>
                                        </span>
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                                        <input type="text" name="category" value="{{ $o->category }}" required
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    </div>
                                    <div class="md:col-span-4">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Label</label>
                                        <input type="text" name="label" value="{{ $o->label }}" required
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Public HT</label>
                                        <input type="number" step="0.01" min="0" name="price" value="{{ $o->price }}" required
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                                        <input type="number" step="0.01" min="0" name="cost" value="{{ $o->cost }}"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    </div>
                                    <div class="md:col-span-1">
                                        <button class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 text-xs font-medium bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                            <i class="ri-save-line"></i>
                                        </button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('catalogue.options.destroy', $o->_id) }}"
                                    onsubmit="return confirm('Remove «{{ $o->label }}»?');" class="-mt-1 text-right">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline"><i class="ri-delete-bin-line"></i> Remove</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div x-data="{ open: false }" class="border-t border-gray-100 pt-4">
                    <button type="button" @click="open = !open" class="text-sm font-medium text-primary-800 hover:underline">
                        <i class="ri-add-line"></i> Add custom option
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('catalogue.options.store', $model->_id) }}"
                        class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        @csrf
                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Category *</label>
                            <input type="text" name="category" required placeholder="e.g. Electronics"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Label *</label>
                            <input type="text" name="label" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Public HT *</label>
                            <input type="number" step="0.01" min="0" name="price" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                            <input type="number" step="0.01" min="0" name="cost"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-1">
                            <button class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 text-xs font-medium bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                <i class="ri-add-line"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
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
                        alert('Could not add the brand. Try again or refresh the page.');
                    } finally {
                        this.adding = false;
                    }
                },
            };
        }

        // Repeater state for the create-mode page (versions + custom options).
        // Each version owns its own `equipment` array — populated via the
        // paste-modal targeting `modalIndex`.
        function boatCreator() {
            return {
                versions: [{ name: '', base_price: '', cost: '', currency: 'EUR', equipment: [] }],
                newOptions: [],

                // Equipment paste modal state — single modal instance shared
                // across all version rows; `modalIndex` says which version
                // we're editing.
                equipmentModalOpen: false,
                modalIndex: null,
                equipmentPasteBuffer: '',

                addVersion() {
                    this.versions.push({ name: '', base_price: '', cost: '', currency: 'EUR', equipment: [] });
                },
                addOption() {
                    this.newOptions.push({ category: '', label: '', price: '', cost: '' });
                },

                openEquipmentModal(i) {
                    this.modalIndex = i;
                    this.equipmentPasteBuffer = '';
                    this.equipmentModalOpen = true;
                },
                closeEquipmentModal() {
                    this.equipmentModalOpen = false;
                    this.modalIndex = null;
                    this.equipmentPasteBuffer = '';
                },
                get equipmentPreview() {
                    if (! this.equipmentPasteBuffer.trim()) return [];
                    return this.equipmentPasteBuffer
                        .split(/\r?\n/)
                        .map(s => s.trim())
                        .filter(s => s.length > 0);
                },
                parseEquipmentPaste() {
                    // No-op — the preview is reactive on equipmentPasteBuffer.
                    // Bound to Enter so Enter doesn't submit the outer form.
                },
                confirmEquipmentPaste() {
                    if (this.modalIndex === null) return;
                    const lines = this.equipmentPreview;
                    if (lines.length === 0) {
                        this.closeEquipmentModal();
                        return;
                    }
                    const existing = new Set(
                        (this.versions[this.modalIndex].equipment || []).map(s => s.toLowerCase())
                    );
                    const next = [...(this.versions[this.modalIndex].equipment || [])];
                    for (const line of lines) {
                        if (existing.has(line.toLowerCase())) continue;
                        next.push(line);
                        existing.add(line.toLowerCase());
                    }
                    this.versions[this.modalIndex].equipment = next;
                    this.closeEquipmentModal();
                },
            };
        }

        // Edit-mode variants editor — owns the shared paste modal for all
        // variant rows. Each row registers its `equipment` array reference
        // here so the modal can mutate the right variant's list on commit.
        function variantsEditor() {
            return {
                modalOpen: false,
                target: null,        // ref to { equipment: [...] }
                pasteBuffer: '',

                register(el, equipment) {
                    // No-op placeholder; the row's reactive data is exposed
                    // to openModalFor() via $data so we don't need to track
                    // it here.
                },

                openModalFor(rowData) {
                    this.target = rowData;
                    this.pasteBuffer = '';
                    this.modalOpen = true;
                },
                closeModal() {
                    this.modalOpen = false;
                    this.target = null;
                    this.pasteBuffer = '';
                },
                get preview() {
                    if (! this.pasteBuffer.trim()) return [];
                    return this.pasteBuffer
                        .split(/\r?\n/)
                        .map(s => s.trim())
                        .filter(s => s.length > 0);
                },
                commit() {
                    if (! this.target) { this.closeModal(); return; }
                    const lines = this.preview;
                    if (lines.length === 0) { this.closeModal(); return; }
                    const existing = new Set((this.target.equipment || []).map(s => s.toLowerCase()));
                    const next = [...(this.target.equipment || [])];
                    for (const line of lines) {
                        if (existing.has(line.toLowerCase())) continue;
                        next.push(line);
                        existing.add(line.toLowerCase());
                    }
                    // Mutate in place so all references stay reactive.
                    this.target.equipment.splice(0, this.target.equipment.length, ...next);
                    this.closeModal();
                },
            };
        }

        // Drag-and-drop reorder for the per-boat options list. Uses SortableJS
        // loaded on demand from CDN — keeps page weight off the global bundle.
        function optionsSortable(reorderUrl) {
            return {
                reorderUrl,
                sortable: null,
                csrf: document.querySelector('meta[name="csrf-token"]')?.content,

                async init(root) {
                    await this.ensureSortable();
                    this.sortable = window.Sortable.create(root, {
                        handle: '.opt-handle',
                        draggable: '.opt-row',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: () => this.persist(root),
                    });
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

                async persist(root) {
                    const ids = Array.from(root.querySelectorAll('.opt-row')).map(el => el.dataset.id);
                    try {
                        await fetch(this.reorderUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            body: JSON.stringify({ ids }),
                        });
                    } catch (e) {
                        // Silent — the next page load will fall back to the
                        // server-stored positions. Don't block the UI.
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
