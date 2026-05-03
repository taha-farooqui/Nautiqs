<x-app-layout title="New private model" header="New private model">

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    @if ($brands->isEmpty())
        <x-app.empty-state
            icon="ri-building-4-line"
            title="No brands available"
            message="Create or activate a brand first — every model must belong to a brand."
            size="lg" />
        <div class="text-center mt-4">
            <a href="{{ route('catalogue.brands') }}" class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-arrow-right-line"></i> Go to Brands
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('catalogue.models.store') }}"
            class="bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Brand <span class="text-red-500">*</span></label>
                <select name="company_brand_id" required
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="">— Select a brand —</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b->_id }}" @selected($preselected === (string) $b->_id || old('company_brand_id') === (string) $b->_id)>
                            {{ $b->name }}{{ $b->source === 'private' ? ' (private)' : '' }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('company_brand_id')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Model code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" required
                        value="{{ old('code') }}"
                        placeholder="e.g. SG250"
                        class="w-full rounded-lg border-gray-300 font-mono focus:border-primary-800 focus:ring-primary-800" />
                    <p class="text-xs text-gray-500 mt-1">Used as the business identifier for imports and reports.</p>
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default margin (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="default_margin_pct"
                        value="{{ old('default_margin_pct') }}"
                        placeholder="20.0"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <p class="text-xs text-gray-500 mt-1">Optional — overrides company default for this model.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Commercial name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required
                    value="{{ old('name') }}"
                    placeholder="e.g. Eagle 10"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                <a href="{{ route('catalogue.models') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</a>
                <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                    <i class="ri-save-line"></i> Create model
                </button>
            </div>
        </form>
    @endif
</x-app-layout>
