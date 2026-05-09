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
                <button type="button" @click="tab = 'equipment'"
                    :class="tab === 'equipment' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                    <i class="ri-tools-line"></i> Equipment
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
                    <div class="border border-gray-200 rounded-lg p-3 mb-2 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-5">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Version name *</label>
                            <input type="text" :name="`versions[${i}][name]`" x-model="v.name"
                                placeholder="e.g. 2x 200HP" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Public HT *</label>
                            <input type="number" step="0.01" min="0" :name="`versions[${i}][base_price]`" x-model="v.base_price" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                            <input type="number" step="0.01" min="0" :name="`versions[${i}][cost]`" x-model="v.cost"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
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
                </template>

                <button type="button" @click="addVersion()"
                    class="text-sm font-medium text-primary-800 hover:underline mt-2">
                    <i class="ri-add-line"></i> Add version
                </button>
            </section>

            {{-- Equipment tab --}}
            <section x-show="tab === 'equipment'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h2 class="text-base font-semibold text-gray-900">Included equipment</h2>
                <p class="text-xs text-gray-500 -mt-2">Tick what comes standard. From the platform library.</p>
                @include('catalogue.partials._equipment-checkboxes', ['model' => $model, 'libraryEquipment' => $libraryEquipment])
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
            <button type="button" @click="tab = 'equipment'"
                :class="tab === 'equipment' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-tools-line"></i> Equipment
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
        <section x-show="tab === 'versions'" x-cloak class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Versions</h2>
                @if ($variants->isEmpty())
                    <p class="text-sm text-gray-500 italic mb-4">No versions yet — add the first one below.</p>
                @else
                    <div class="space-y-3 mb-6">
                        @foreach ($variants as $v)
                            <form method="POST" action="{{ route('catalogue.variants.update', $v->_id) }}"
                                class="border border-gray-200 rounded-lg p-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                @csrf @method('PATCH')
                                <div class="md:col-span-5">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Version name</label>
                                    <input type="text" name="name" value="{{ $v->name }}" required
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Public HT</label>
                                    <input type="number" step="0.01" min="0" name="base_price" value="{{ $v->base_price }}" required
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                                    <input type="number" step="0.01" min="0" name="cost" value="{{ $v->cost }}"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
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
                            </form>
                            <form method="POST" action="{{ route('catalogue.variants.destroy', $v->_id) }}"
                                onsubmit="return confirm('Remove «{{ $v->name }}»?');" class="-mt-2 text-right">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline"><i class="ri-delete-bin-line"></i> Remove</button>
                            </form>
                        @endforeach
                    </div>
                @endif

                <div x-data="{ open: false }" class="border-t border-gray-100 pt-4">
                    <button type="button" @click="open = !open" class="text-sm font-medium text-primary-800 hover:underline">
                        <i class="ri-add-line"></i> Add version
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('catalogue.variants.store', $model->_id) }}"
                        class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        @csrf
                        <div class="md:col-span-5">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" name="name" required placeholder="e.g. IDEA60 — 2x 200HP"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Public HT *</label>
                            <input type="number" step="0.01" min="0" name="base_price" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                            <input type="number" step="0.01" min="0" name="cost"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
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
                    </form>
                </div>
            </div>
        </section>

        {{-- Equipment tab --}}
        <section x-show="tab === 'equipment'" x-cloak class="space-y-4">
            <form method="POST" action="{{ route('catalogue.models.update', $model->_id) }}"
                class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                @csrf @method('PATCH')

                {{-- Carry critical fields so the PATCH validator passes. --}}
                <input type="hidden" name="code" value="{{ $model->code }}" />
                <input type="hidden" name="name" value="{{ $model->name }}" />
                <input type="hidden" name="company_brand_id" value="{{ $model->company_brand_id }}" />
                <input type="hidden" name="is_active" value="{{ $model->is_active ? '1' : '0' }}" />

                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Included equipment</h2>
                        <p class="text-xs text-gray-500">Tick what comes standard with this boat. From the platform library.</p>
                    </div>
                    <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                        <i class="ri-save-line"></i> Save selection
                    </button>
                </div>

                @include('catalogue.partials._equipment-checkboxes', ['model' => $model, 'libraryEquipment' => $libraryEquipment])
            </form>
        </section>

        {{-- Options tab --}}
        <section x-show="tab === 'options'" x-cloak class="space-y-4">
            {{-- Library picker --}}
            <div x-data="{ open: false, picked: [] }" class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Pick from library</h3>
                        <p class="text-xs text-gray-500">Add ready-made options to this boat. Adjust each price after.</p>
                    </div>
                    <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg">
                        <i class="ri-archive-stack-line"></i> Browse library
                    </button>
                </div>

                <form x-show="open" x-cloak x-transition.opacity method="POST"
                      action="{{ route('catalogue.options.import', $model->_id) }}" class="mt-4">
                    @csrf
                    <div class="max-h-72 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100 mb-3">
                        @foreach ($libraryOptions->groupBy('category') as $category => $items)
                            <div>
                                <div class="px-3 py-2 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">{{ $category }}</div>
                                @foreach ($items as $opt)
                                    <label class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <input type="checkbox" name="option_ids[]" value="{{ $opt->_id }}" x-model="picked"
                                                class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                                            <span class="text-sm text-gray-900 truncate">{{ $opt->label }}</span>
                                        </div>
                                        <span class="text-sm text-gray-700 font-semibold whitespace-nowrap">€{{ number_format($opt->price, 0, ',', ' ') }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="picked.length === 0"
                            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50">
                            <i class="ri-add-line"></i> Add <span x-text="picked.length"></span> selected
                        </button>
                    </div>
                </form>
            </div>

            {{-- Per-boat options list --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Options on this boat</h2>
                @if ($options->isEmpty())
                    <p class="text-sm text-gray-500 italic mb-4">No options yet — pick from the library above or add a custom one below.</p>
                @else
                    <div class="space-y-2 mb-6">
                        @foreach ($options as $o)
                            <form method="POST" action="{{ route('catalogue.options.update', $o->_id) }}"
                                class="border border-gray-200 rounded-lg p-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                @csrf @method('PATCH')
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
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Pos.</label>
                                    <input type="number" name="position" value="{{ $o->position }}"
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
                        <div class="md:col-span-1">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                            <input type="number" step="0.01" min="0" name="cost"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Pos.</label>
                            <input type="number" name="position" value="0"
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
        function boatCreator() {
            return {
                versions: [{ name: '', base_price: '', cost: '', currency: 'EUR' }],
                newOptions: [],
                addVersion() {
                    this.versions.push({ name: '', base_price: '', cost: '', currency: 'EUR' });
                },
                addOption() {
                    this.newOptions.push({ category: '', label: '', price: '', cost: '' });
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
