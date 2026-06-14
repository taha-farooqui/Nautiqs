<x-app-layout :title="$engine ? __('Edit engine') : __('Add engine')" :header="$engine ? __('Edit engine') : __('Add engine')">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('engines.index') }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to engines') }}
        </a>
    </div>

    <form method="POST"
        action="{{ $engine ? route('engines.update', $engine->_id) : route('engines.store') }}"
        class="bg-white rounded-2xl border border-gray-200 p-6 max-w-3xl space-y-5">
        @csrf
        @if ($engine) @method('PATCH') @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Brand picker: same brand source as the boat creator
                 (catalogue.brands.lookup → workspace + global brands), so
                 every brand available for boats is available for engines.
                 Free-typing a brand not in the list is still allowed. --}}
            <div x-data="engineBrandPicker(@js(old('brand', $engine->brand ?? '')))" class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brand') }} <span class="text-red-500">*</span></label>
                <input type="text" name="brand" required x-model="query" autocomplete="off"
                    @focus="search()" @input.debounce.200ms="search()"
                    @keydown.escape="open = false"
                    placeholder="{{ __('Type to search brands…') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <div x-show="open" x-cloak @click.outside="open = false"
                    class="absolute left-0 right-0 z-30 mt-1 bg-white rounded-lg border border-gray-200 shadow-lg max-h-56 overflow-y-auto">
                    <template x-for="b in results" :key="b.id">
                        <button type="button" @click="pick(b.name)"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-primary-50 hover:text-primary-900"
                            x-text="b.name"></button>
                    </template>
                    <template x-if="results.length === 0 && query.length > 0">
                        <div class="px-3 py-2 text-sm text-gray-400 italic">{{ __('Type a new brand') }}</div>
                    </template>
                </div>
                <x-input-error :messages="$errors->get('brand')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Horsepower') }}</label>
                <input type="number" step="1" min="0" name="horsepower"
                    value="{{ old('horsepower', $engine->horsepower ?? '') }}"
                    placeholder="200"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                <select name="currency" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="EUR" @selected(old('currency', $engine->currency ?? 'EUR') === 'EUR')>EUR</option>
                    <option value="USD" @selected(old('currency', $engine->currency ?? '') === 'USD')>USD</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Cost (dealer)') }}</label>
                <input type="number" step="0.01" min="0" name="cost"
                    value="{{ old('cost', $engine->cost ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <p class="text-xs text-gray-500 mt-1">{{ __('Internal — never shown to clients.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Public HT') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="price" required
                    value="{{ old('price', $engine->price ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('price')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT rate (%)') }}</label>
                <input type="number" step="0.1" min="0" max="100" name="vat_rate"
                    value="{{ old('vat_rate', $engine->vat_rate ?? '20') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('engines.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $engine ? __('Save changes') : __('Add engine') }}
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        // Brand picker for engines — queries the SAME endpoint the boat
        // creator uses (catalogue.brands.lookup), so workspace + global
        // brands appear here too. Stores the chosen brand NAME into the
        // `brand` field; free-typing a new brand is allowed.
        function engineBrandPicker(initial) {
            return {
                query: initial || '',
                results: [],
                open: false,
                async search() {
                    this.open = true;
                    try {
                        const url = '{{ route('catalogue.brands.lookup') }}?q=' + encodeURIComponent(this.query);
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        this.results = res.ok ? await res.json() : [];
                    } catch (e) {
                        this.results = [];
                    }
                },
                pick(name) {
                    this.query = name;
                    this.open = false;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
