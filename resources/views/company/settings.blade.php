<x-app-layout title="Company settings" header="Company settings">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <form action="{{ route('company.settings.update') }}" method="POST" class="max-w-4xl space-y-5">
        @csrf
        @method('PATCH')

        {{-- §17.1 Company profile & legal details --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">Company profile</h3>
            <p class="text-xs text-gray-500 mb-4">Displayed in all PDFs and emails (§17.1).</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $company->name) }}" required
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Legal form</label>
                    <input type="text" name="legal_form" value="{{ old('legal_form', $company->legal_form) }}"
                        placeholder="SAS, SARL, SA, EI…"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SIREN</label>
                    <input type="text" name="siren" value="{{ old('siren', $company->siren) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">VAT number</label>
                    <input type="text" name="vat_number" value="{{ old('vat_number', $company->vat_number) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="3"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">{{ old('address', $company->address) }}</textarea>
                </div>
            </div>
        </div>

        {{-- §17.2 Salesperson --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">Salesperson</h3>
            <p class="text-xs text-gray-500 mb-4">Appears in PDF header and signature block (§17.2).</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="salesperson_name" value="{{ old('salesperson_name', $company->salesperson_name) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" name="salesperson_phone" value="{{ old('salesperson_phone', $company->salesperson_phone) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="salesperson_email" value="{{ old('salesperson_email', $company->salesperson_email) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
        </div>

        {{-- §17.3 Defaults --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">Defaults</h3>
            <p class="text-xs text-gray-500 mb-4">Overridable per quote (§17.3).</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">VAT rate %</label>
                    <input type="number" step="0.1" name="default_vat_rate" value="{{ old('default_vat_rate', $company->default_vat_rate) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default global margin %</label>
                    <input type="number" step="0.1" name="default_margin_pct" value="{{ old('default_margin_pct', $company->default_margin_pct) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display mode</label>
                    <select name="default_display_mode"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                        <option value="TTC" @selected(old('default_display_mode', $company->default_display_mode) === 'TTC')>TTC (incl. VAT)</option>
                        <option value="HT"  @selected(old('default_display_mode', $company->default_display_mode) === 'HT')>HT (excl. VAT)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- §17.4 Margin presets --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">Margin presets</h3>
            <p class="text-xs text-gray-500 mb-4">Per-category fallback margin when no real cost is provided (§17.4).</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach (['hull' => 'Hull', 'engine' => 'Engine', 'options' => 'Options', 'custom_items' => 'Custom items'] as $key => $label)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} %</label>
                        <input type="number" step="0.1" name="margin_presets[{{ $key }}]"
                            value="{{ old('margin_presets.' . $key, $company->margin_presets[$key] ?? '') }}"
                            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                <i class="ri-save-line"></i> Save changes
            </button>
        </div>
    </form>
</x-app-layout>
