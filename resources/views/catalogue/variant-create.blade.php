<x-app-layout title="Add variant" header="Add variant">

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('catalogue.models', ['tab' => 'workspace']) }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> Back to models &amp; variants
        </a>
    </div>

    @if ($brands->isEmpty())
        <x-app.empty-state
            icon="ri-building-4-line"
            title="No brands in your workspace"
            message="Activate or create a brand first — every variant must belong to a brand and a model."
            size="lg" />
        <div class="text-center mt-4">
            <a href="{{ route('catalogue.brands') }}" class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-arrow-right-line"></i> Go to Brands
            </a>
        </div>
    @else
        {{-- Step 1: pick brand & model. We use a tiny GET form so changing the brand
             reloads the page with the right model dropdown; keeps everything server-rendered. --}}
        <form method="GET" action="{{ route('catalogue.variants.create') }}"
            class="bg-white rounded-2xl border border-gray-200 p-5 mb-4">
            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">1</span>
                Pick brand
            </h3>
            <select name="brand" onchange="this.form.submit()"
                class="w-full max-w-md rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                <option value="">— Select a brand —</option>
                @foreach ($brands as $b)
                    <option value="{{ $b->_id }}" @selected($brandId === (string) $b->_id)>
                        {{ $b->name }}{{ $b->source === 'private' ? ' (private)' : '' }}
                    </option>
                @endforeach
            </select>
        </form>

        @if ($brandId)
            <form method="POST" action="{{ route('catalogue.variants.store-standalone') }}"
                x-data="{
                    equipment: [{ label: '', type: 'standard' }],
                    add() { this.equipment.push({ label: '', type: 'standard' }) },
                    remove(i) { this.equipment.splice(i, 1) }
                }"
                class="space-y-4">
                @csrf
                <input type="hidden" name="company_brand_id" value="{{ $brandId }}" />

                {{-- Step 2: model --}}
                <section class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">2</span>
                        Pick model
                    </h3>
                    @if ($models->isEmpty())
                        <p class="text-sm text-gray-500 mb-3">This brand doesn't have any models yet.</p>
                        <a href="{{ route('catalogue.models.create', ['brand' => $brandId]) }}"
                            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 rounded-lg">
                            <i class="ri-add-line"></i> Create a model first
                        </a>
                    @else
                        <select name="company_model_id" required
                            class="w-full max-w-md rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                            <option value="">— Select a model —</option>
                            @foreach ($models as $m)
                                <option value="{{ $m->_id }}" @selected(old('company_model_id') === (string) $m->_id)>
                                    {{ $m->name }} ({{ $m->code }})
                                </option>
                            @endforeach
                        </select>
                    @endif
                </section>

                @if ($models->isNotEmpty())
                    {{-- Step 3: variant fields --}}
                    <section class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">3</span>
                            Variant details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Variant name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" required
                                    value="{{ old('name') }}"
                                    placeholder="e.g. Eagle 10 — 2x 250HP"
                                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Base price (excl. VAT) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" min="0" name="base_price" required
                                    value="{{ old('base_price') }}"
                                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                                <x-input-error :messages="$errors->get('base_price')" class="mt-1" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cost</label>
                                <input type="number" step="0.01" min="0" name="cost"
                                    value="{{ old('cost') }}"
                                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                                <select name="currency" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                                    <option value="EUR" @selected(old('currency', 'EUR') === 'EUR')>EUR</option>
                                    <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    {{-- Step 4: standard equipment --}}
                    <section class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="font-semibold text-gray-900 mb-1 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">4</span>
                            Included equipment
                        </h3>
                        <p class="text-sm text-gray-500 mb-3">
                            Standard items that ship with this variant. Use «free text» for one-off notes (e.g. "preparation included").
                        </p>

                        <template x-for="(row, i) in equipment" :key="i">
                            <div class="flex items-end gap-2 mb-2">
                                <div class="flex-1">
                                    <input type="text" :name="`equipment[${i}][label]`" x-model="row.label"
                                        placeholder="e.g. Bimini top, GPS chartplotter, …"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </div>
                                <div class="w-40">
                                    <select :name="`equipment[${i}][type]`" x-model="row.type"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                        <option value="standard">Standard</option>
                                        <option value="free_text">Free text</option>
                                    </select>
                                </div>
                                <button type="button" @click="remove(i)"
                                    class="px-2.5 py-2 text-xs font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg shrink-0">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </template>

                        <button type="button" @click="add()"
                            class="text-sm font-medium text-primary-800 hover:underline">
                            <i class="ri-add-line"></i> Add equipment row
                        </button>
                    </section>

                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('catalogue.models', ['tab' => 'workspace']) }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</a>
                        <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                            <i class="ri-save-line"></i> Add variant
                        </button>
                    </div>
                @endif
            </form>
        @endif
    @endif
</x-app-layout>
