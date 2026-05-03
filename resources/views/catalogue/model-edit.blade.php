<x-app-layout :title="$model->name" :header="$model->name">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('catalogue.models') }}" class="hover:text-primary-800"><i class="ri-arrow-left-line"></i> Back to models</a>
        <span>·</span>
        <span class="font-mono px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">{{ $model->code }}</span>
        @if ($model->source === 'private')
            <span class="px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 text-[11px] font-semibold">Private</span>
        @else
            <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 text-[11px] font-semibold">From global</span>
        @endif
    </div>

    {{-- Model details --}}
    <section class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Model details</h2>
        <form method="POST" action="{{ route('catalogue.models.update', $model->_id) }}" class="space-y-4">
            @csrf @method('PATCH')
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                    <input type="text" name="code" value="{{ old('code', $model->code) }}" class="w-full rounded-lg border-gray-300 font-mono focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $model->name) }}" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default margin (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="default_margin_pct" value="{{ old('default_margin_pct', $model->default_margin_pct) }}" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
            <div class="flex justify-end">
                <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                    <i class="ri-save-line"></i> Save changes
                </button>
            </div>
        </form>
    </section>

    {{-- Variants --}}
    <section class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900">Variants <span class="text-gray-400 text-sm font-normal">({{ $variants->count() }})</span></h2>
        </div>

        @if ($variants->isEmpty())
            <p class="text-sm text-gray-500 italic mb-4">No variants yet — add the first one below.</p>
        @else
            <div class="space-y-3 mb-6">
                @foreach ($variants as $v)
                    <form method="POST" action="{{ route('catalogue.variants.update', $v->_id) }}"
                        class="border border-gray-200 rounded-lg p-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        @csrf @method('PATCH')
                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="name" value="{{ $v->name }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Base price</label>
                            <input type="number" step="0.01" min="0" name="base_price" value="{{ $v->base_price }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                            <input type="number" step="0.01" min="0" name="cost" value="{{ $v->cost }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
                            <select name="currency" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                <option value="EUR" @selected($v->currency === 'EUR')>EUR</option>
                                <option value="USD" @selected($v->currency === 'USD')>USD</option>
                            </select>
                        </div>
                        <div class="md:col-span-2 flex items-center gap-2 justify-end">
                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-2 text-xs font-medium bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                <i class="ri-save-line"></i> Save
                            </button>
                        </div>

                        <div class="md:col-span-12 flex items-center justify-between text-xs text-gray-500 pt-1 border-t border-gray-100">
                            <span>
                                @if ($v->source === 'private')
                                    <span class="text-purple-700 font-medium">Private variant</span>
                                @else
                                    <span class="text-blue-700">From global · customisations stay yours</span>
                                @endif
                            </span>
                        </div>
                    </form>

                    {{-- Separate delete form so submit doesn't trigger the update --}}
                    <form method="POST" action="{{ route('catalogue.variants.destroy', $v->_id) }}"
                        onsubmit="return confirm('Remove «{{ $v->name }}»?');" class="-mt-2 text-right">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-600 hover:underline"><i class="ri-delete-bin-line"></i> Remove</button>
                    </form>
                @endforeach
            </div>
        @endif

        {{-- Add variant --}}
        <div x-data="{ open: false }" class="border-t border-gray-100 pt-4">
            <button type="button" @click="open = !open" class="text-sm font-medium text-primary-800 hover:underline">
                <i class="ri-add-line"></i> Add variant
            </button>
            <form x-show="open" x-cloak method="POST" action="{{ route('catalogue.variants.store', $model->_id) }}"
                class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                @csrf
                <div class="md:col-span-5">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" required placeholder="e.g. 250 Sport — 2x 200HP" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Base price *</label>
                    <input type="number" step="0.01" min="0" name="base_price" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                    <input type="number" step="0.01" min="0" name="cost" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
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
    </section>

    {{-- Options --}}
    <section class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900">Options <span class="text-gray-400 text-sm font-normal">({{ $options->count() }})</span></h2>
        </div>

        @if ($options->isEmpty())
            <p class="text-sm text-gray-500 italic mb-4">No options yet — add the first one below.</p>
        @else
            <div class="space-y-2 mb-6">
                @foreach ($options as $o)
                    <form method="POST" action="{{ route('catalogue.options.update', $o->_id) }}"
                        class="border border-gray-200 rounded-lg p-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        @csrf @method('PATCH')
                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                            <input type="text" name="category" value="{{ $o->category }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Label</label>
                            <input type="text" name="label" value="{{ $o->label }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Price</label>
                            <input type="number" step="0.01" min="0" name="price" value="{{ $o->price }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                            <input type="number" step="0.01" min="0" name="cost" value="{{ $o->cost }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Pos.</label>
                            <input type="number" name="position" value="{{ $o->position }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
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
                <i class="ri-add-line"></i> Add option
            </button>
            <form x-show="open" x-cloak method="POST" action="{{ route('catalogue.options.store', $model->_id) }}"
                class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                @csrf
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Category *</label>
                    <input type="text" name="category" required placeholder="e.g. Electronics" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Label *</label>
                    <input type="text" name="label" required placeholder="Garmin GPS chartplotter 9&quot;" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Price *</label>
                    <input type="number" step="0.01" min="0" name="price" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Cost</label>
                    <input type="number" step="0.01" min="0" name="cost" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Pos.</label>
                    <input type="number" name="position" value="0" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-1">
                    <button class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 text-xs font-medium bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                        <i class="ri-add-line"></i>
                    </button>
                </div>
            </form>
        </div>
    </section>

    @if ($model->source === 'private')
        <section class="bg-white rounded-2xl border border-red-200 p-6">
            <h2 class="text-base font-semibold text-red-700 mb-2">Danger zone</h2>
            <p class="text-sm text-gray-600 mb-3">Deleting this private model removes its variants and options too. Quotes already created with this model are preserved (they store snapshots).</p>
            <form method="POST" action="{{ route('catalogue.models.destroy', $model->_id) }}"
                onsubmit="return confirm('Delete «{{ $model->name }}» and all its variants/options?');">
                @csrf @method('DELETE')
                <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg">
                    <i class="ri-delete-bin-line"></i> Delete model
                </button>
            </form>
        </section>
    @endif
</x-app-layout>
