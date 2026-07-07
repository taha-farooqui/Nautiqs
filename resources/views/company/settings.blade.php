<x-app-layout :title="__('Company settings')" :header="__('Company settings')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    @php
        // Auto-jump to the tab that owns whichever field failed validation,
        // otherwise default to Profile. Order of these checks matches the
        // declared tabs below.
        $errorMap = [
            'profile'  => ['name','legal_form','siren','vat_number','address','logo'],
            'sales'    => ['salesperson_name','salesperson_phone','salesperson_email'],
            'defaults' => ['default_vat_rate','default_margin_pct','default_display_mode','timezone'],
            'margins'  => ['margin_presets.hull','margin_presets.engine','margin_presets.options','margin_presets.custom_items'],
            'followup' => ['follow_up_enabled','follow_up_delay_value','follow_up_delay_unit'],
        ];
        $startTab = 'profile';
        foreach ($errorMap as $tab => $fields) {
            if (collect($fields)->some(fn ($f) => $errors->has($f))) { $startTab = $tab; break; }
        }
    @endphp

    <form action="{{ route('company.settings.update') }}" method="POST" class="max-w-4xl"
          enctype="multipart/form-data"
          x-data="{ tab: @js($startTab) }">
        @csrf
        @method('PATCH')

        {{-- Tab strip --}}
        <div class="mb-4 border-b border-gray-200 flex items-center gap-1 overflow-x-auto">
            <button type="button" @click="tab = 'profile'"
                :class="tab === 'profile' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-building-line"></i> {{ __('Profile') }}
            </button>
            <button type="button" @click="tab = 'sales'"
                :class="tab === 'sales' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-user-star-line"></i> {{ __('Salesperson') }}
            </button>
            <button type="button" @click="tab = 'defaults'"
                :class="tab === 'defaults' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-settings-3-line"></i> {{ __('Defaults') }}
            </button>
            <button type="button" @click="tab = 'margins'"
                :class="tab === 'margins' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-percent-line"></i> {{ __('Margin presets') }}
            </button>
            <button type="button" @click="tab = 'followup'"
                :class="tab === 'followup' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap">
                <i class="ri-mail-send-line"></i> {{ __('Follow-ups') }}
            </button>
        </div>

        {{-- ============================== PROFILE ============================== --}}
        <section x-show="tab === 'profile'" class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Company profile') }}</h3>
            <p class="text-xs text-gray-500 mb-4">{{ __('Displayed in all PDFs and emails.') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Company name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $company->name) }}" required
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Legal form') }}</label>
                    <input type="text" name="legal_form" value="{{ old('legal_form', $company->legal_form) }}"
                        placeholder="{{ __('SAS, SARL, SA, EI…') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('SIREN') }}</label>
                    <input type="text" name="siren" value="{{ old('siren', $company->siren) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT number') }}</label>
                    <input type="text" name="vat_number" value="{{ old('vat_number', $company->vat_number) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Address') }}</label>
                    <textarea name="address" rows="3"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">{{ old('address', $company->address) }}</textarea>
                </div>

                {{-- Dealership logo — shown in the header of quote/order PDFs. --}}
                <div class="md:col-span-2 pt-2 border-t border-gray-100">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Logo') }}</label>
                    <p class="text-xs text-gray-500 mb-2">{{ __('Displayed at the top of your quote and order PDFs. PNG, JPG or WebP — max 2 MB.') }}</p>

                    @if ($company->logo_path)
                        <div class="flex items-center gap-4 mb-3">
                            {{-- Inline sizes on purpose: arbitrary Tailwind classes aren't in
                                 the compiled CSS bundle, so constrain the preview directly. --}}
                            <div class="rounded-lg border border-gray-200 bg-gray-50 flex items-center"
                                style="height:64px; padding:8px 12px;">
                                <img src="{{ asset('storage/' . $company->logo_path) }}" alt="{{ $company->name }}"
                                    style="max-height:48px; max-width:180px; width:auto; height:auto; object-fit:contain; display:block;" />
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-red-600 cursor-pointer">
                                <input type="checkbox" name="remove_logo" value="1"
                                    class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
                                {{ __('Remove logo') }}
                            </label>
                        </div>
                    @endif

                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                        class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-800 hover:file:bg-primary-100" />
                    <x-input-error :messages="$errors->get('logo')" class="mt-1" />
                </div>
            </div>
        </section>

        {{-- ============================== SALESPERSON ============================== --}}
        <section x-show="tab === 'sales'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Salesperson') }}</h3>
            <p class="text-xs text-gray-500 mb-4">{{ __('Appears in PDF header and email signature.') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }}</label>
                    <input type="text" name="salesperson_name" value="{{ old('salesperson_name', $company->salesperson_name) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                    <input type="tel" name="salesperson_phone" value="{{ old('salesperson_phone', $company->salesperson_phone) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                    <input type="email" name="salesperson_email" value="{{ old('salesperson_email', $company->salesperson_email) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
        </section>

        {{-- ============================== DEFAULTS ============================== --}}
        <section x-show="tab === 'defaults'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Defaults') }}</h3>
            <p class="text-xs text-gray-500 mb-4">{{ __('Overridable per quote.') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT rate %') }}</label>
                    <input type="number" step="0.1" name="default_vat_rate" value="{{ old('default_vat_rate', $company->default_vat_rate) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Default global margin %') }}</label>
                    <input type="number" step="0.1" name="default_margin_pct" value="{{ old('default_margin_pct', $company->default_margin_pct) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Display mode') }}</label>
                    <select name="default_display_mode"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                        <option value="TTC" @selected(old('default_display_mode', $company->default_display_mode) === 'TTC')>{{ __('TTC (incl. VAT)') }}</option>
                        <option value="HT"  @selected(old('default_display_mode', $company->default_display_mode) === 'HT')>{{ __('HT (excl. VAT)') }}</option>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Timezone') }}</label>
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
                    {{ __('All dates and times shown in the app render in this timezone. Stored as UTC; display only.') }}
                </p>
                <x-input-error :messages="$errors->get('timezone')" class="mt-1" />
            </div>

            {{-- USD→EUR conversion rate — read-only. Sourced live from the
                 European Central Bank daily reference rate (FxRateService). --}}
            <div class="mt-6 pt-5 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('USD → EUR conversion rate') }}</label>
                <div class="flex items-center gap-3 max-w-md">
                    <div class="flex-1 flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <span class="text-sm text-gray-700">$1 =</span>
                        <span class="text-sm font-semibold text-gray-900">
                            @if ($usdEur)
                                {{ number_format($usdEur, 4, ',', ' ') }} €
                            @else
                                {{ __('unavailable') }}
                            @endif
                        </span>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold shrink-0">
                        <i class="ri-global-line"></i> {{ __('Live rate') }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __('Read-only. Fetched automatically from a live API (European Central Bank daily rates) and used to convert USD prices to EUR in quotes.') }}
                </p>
            </div>
        </section>

        {{-- ============================== MARGIN PRESETS ============================== --}}
        <section x-show="tab === 'margins'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Margin presets') }}</h3>
            <p class="text-xs text-gray-500 mb-4">{{ __('Per-category fallback margin when no real cost is provided.') }}</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @php
                    $marginLabels = [
                        'hull'         => __('Hull'),
                        'engine'       => __('Engine'),
                        'options'      => __('Options'),
                        'custom_items' => __('Custom items'),
                    ];
                @endphp
                @foreach ($marginLabels as $key => $label)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} %</label>
                        <input type="number" step="0.1" name="margin_presets[{{ $key }}]"
                            value="{{ old('margin_presets.' . $key, $company->margin_presets[$key] ?? '') }}"
                            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============================== FOLLOW-UPS (Relances) ============================== --}}
        <section x-show="tab === 'followup'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6"
            x-data="{ fuEnabled: @js((bool) old('follow_up_enabled', $company->follow_up_enabled ?? false)) }">
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Automatic follow-up') }}</h3>
            <p class="text-xs text-gray-500 mb-4">{{ __('Automatically remind clients who have not replied to a quote.') }}</p>

            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="follow_up_enabled" value="0" />
                <input type="checkbox" name="follow_up_enabled" value="1" x-model="fuEnabled"
                    class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                <span class="text-sm font-medium text-gray-800">{{ __('Enable automatic follow-up') }}</span>
            </label>

            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-700"
                :class="fuEnabled ? '' : 'opacity-50 pointer-events-none'">
                <span>{{ __('Send an automatic follow-up') }}</span>
                <input type="number" min="1" max="365" step="1" name="follow_up_delay_value"
                    value="{{ old('follow_up_delay_value', $company->follow_up_delay_value ?? 1) }}"
                    class="w-20 rounded-lg border-gray-300 text-sm text-center focus:border-primary-800 focus:ring-primary-800" />
                @php $fuUnit = old('follow_up_delay_unit', $company->follow_up_delay_unit ?? 'months'); @endphp
                <select name="follow_up_delay_unit"
                    class="rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                    <option value="days"   @selected($fuUnit === 'days')>{{ __('day(s)') }}</option>
                    <option value="weeks"  @selected($fuUnit === 'weeks')>{{ __('week(s)') }}</option>
                    <option value="months" @selected($fuUnit === 'months')>{{ __('month(s)') }}</option>
                </select>
                <span>{{ __('after the quote is sent.') }}</span>
            </div>
            <x-input-error :messages="$errors->get('follow_up_delay_value')" class="mt-1" />
            <x-input-error :messages="$errors->get('follow_up_delay_unit')" class="mt-1" />

            <ul class="mt-5 pt-4 border-t border-gray-100 text-xs text-gray-500 space-y-1.5 list-disc list-inside">
                <li>{{ __('One single follow-up is sent per quote — never more.') }}</li>
                <li>{{ __('Only quotes sent after activation are followed up; quotes marked Won or Lost are excluded.') }}</li>
                <li>{{ __('You can exclude an individual quote from its page.') }}</li>
                <li>{{ __('Uses your « Follow-up » email template, with the quote PDF attached.') }}</li>
            </ul>
        </section>

        <div class="flex justify-end mt-4">
            <button type="submit"
                class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                <i class="ri-save-line"></i> {{ __('Save changes') }}
            </button>
        </div>
    </form>
</x-app-layout>
