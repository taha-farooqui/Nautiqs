<x-app-layout title="Company settings" header="Company settings">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    @php
        // Auto-jump to the tab that owns whichever field failed validation,
        // otherwise default to Profile. Order of these checks matches the
        // declared tabs below.
        $errorMap = [
            'profile'  => ['name','legal_form','siren','vat_number','address'],
            'sales'    => ['salesperson_name','salesperson_phone','salesperson_email'],
            'defaults' => ['default_vat_rate','default_margin_pct','default_display_mode','timezone'],
            'margins'  => ['margin_presets.hull','margin_presets.engine','margin_presets.options','margin_presets.custom_items'],
        ];
        $startTab = 'profile';
        foreach ($errorMap as $tab => $fields) {
            if (collect($fields)->some(fn ($f) => $errors->has($f))) { $startTab = $tab; break; }
        }
    @endphp

    <form action="{{ route('company.settings.update') }}" method="POST" class="max-w-4xl"
          x-data="{ tab: @js($startTab) }">
        @csrf
        @method('PATCH')

        {{-- Tab strip --}}
        <div class="mb-4 border-b border-gray-200 flex items-center gap-1 overflow-x-auto">
            <button type="button" @click="tab = 'profile'"
                :class="tab === 'profile' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-building-line"></i> Profile
            </button>
            <button type="button" @click="tab = 'sales'"
                :class="tab === 'sales' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-user-star-line"></i> Salesperson
            </button>
            <button type="button" @click="tab = 'defaults'"
                :class="tab === 'defaults' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-settings-3-line"></i> Defaults
            </button>
            <button type="button" @click="tab = 'margins'"
                :class="tab === 'margins' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-percent-line"></i> Margin presets
            </button>
        </div>

        {{-- ============================== PROFILE ============================== --}}
        <section x-show="tab === 'profile'" class="bg-white rounded-2xl border border-gray-200 p-6">
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
        </section>

        {{-- ============================== SALESPERSON ============================== --}}
        <section x-show="tab === 'sales'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
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
        </section>

        {{-- ============================== DEFAULTS ============================== --}}
        <section x-show="tab === 'defaults'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
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

            {{-- Language picker. Hits /locale/{lang}, drops a cookie, redirects back. --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Language') }}</label>
                <select onchange="window.location = this.value"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="{{ route('locale.switch', 'fr') }}" @selected(app()->getLocale() === 'fr')>Français</option>
                    <option value="{{ route('locale.switch', 'en') }}" @selected(app()->getLocale() === 'en')>English</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __('Saved per browser. Reloads the page when changed.') }}
                </p>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                @php
                    $currentTz = old('timezone', $company->timezone ?? config('app.timezone', 'UTC'));
                    $zones = collect(timezone_identifiers_list())
                        ->groupBy(fn ($z) => str_contains($z, '/') ? explode('/', $z)[0] : 'Other')
                        ->sortKeys();
                @endphp
                <select name="timezone" required
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    @foreach ($zones as $region => $list)
                        <optgroup label="{{ $region }}">
                            @foreach ($list as $tz)
                                <option value="{{ $tz }}" @selected($currentTz === $tz)>{{ $tz }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    All dates and times shown in the app render in this timezone. Stored as UTC; display only.
                </p>
                <x-input-error :messages="$errors->get('timezone')" class="mt-1" />
            </div>
        </section>

        {{-- ============================== MARGIN PRESETS ============================== --}}
        <section x-show="tab === 'margins'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
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
        </section>

        <div class="flex justify-end mt-4">
            <button type="submit"
                class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                <i class="ri-save-line"></i> Save changes
            </button>
        </div>
    </form>
</x-app-layout>
