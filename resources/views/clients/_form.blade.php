@props(['client'])

@php
    $isEdit = $client->exists;
    $action = $isEdit ? route('clients.update', $client->_id) : route('clients.store');
    $method = $isEdit ? 'PATCH' : 'POST';
@endphp

<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @method($method)

    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">{{ __('Contact') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('First name') }} <span class="text-red-500">*</span>
                </label>
                <input type="text" name="first_name" value="{{ old('first_name', $client->first_name) }}"
                    required class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Last name') }} <span class="text-red-500">*</span>
                </label>
                <input type="text" name="last_name" value="{{ old('last_name', $client->last_name) }}"
                    required class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Company name') }} <span class="text-gray-400 text-xs font-normal">{{ __('(optional — if client is a business)') }}</span></label>
                <input type="text" name="company_name" value="{{ old('company_name', $client->company_name) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('company_name')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email', $client->email) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                <input type="tel" name="phone" value="{{ old('phone', $client->phone) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">{{ __('Address') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Street address') }}</label>
                <input type="text" name="address_line" value="{{ old('address_line', $client->address_line) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('address_line')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Postal code') }}</label>
                <input type="text" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('postal_code')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('City') }}</label>
                <input type="text" name="city" value="{{ old('city', $client->city) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('city')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Country') }}</label>
                <input type="text" name="country" value="{{ old('country', $client->country ?? 'France') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('country')" class="mt-1" />
            </div>
        </div>
    </div>

    {{-- Sales context: what they sail, what they own, how they reached us.
         Internal only — none of this is printed on a quote. --}}
    @php
        $leadSources = auth()->user()?->company?->leadSources() ?? \App\Models\Client::LEAD_SOURCES;
        $leadCurrent = old('lead_source', $client->lead_source);
        // A source saved before it was added to the list (or removed since)
        // still has to show up selected rather than silently reset to blank.
        if ($leadCurrent && ! collect($leadSources)->contains(fn ($s) => mb_strtolower($s) === mb_strtolower($leadCurrent))) {
            $leadSources[] = $leadCurrent;
        }
    @endphp
    <div class="bg-white rounded-2xl border border-gray-200 p-6"
        x-data="{ adding: false }">
        <h3 class="font-semibold text-gray-900 mb-4">{{ __('Boating profile') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Navigation area') }}</label>
                <input type="text" name="navigation_area" value="{{ old('navigation_area', $client->navigation_area) }}"
                    placeholder="{{ __('e.g. Golfe du Morbihan, Méditerranée') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('navigation_area')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Current boat') }}</label>
                <input type="text" name="current_boat" value="{{ old('current_boat', $client->current_boat) }}"
                    placeholder="{{ __('e.g. Antares 7.80 (2018)') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('current_boat')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Lead source') }}</label>
                <select name="lead_source" x-on:change="adding = ($event.target.value === '__new__')"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="">{{ __('— Not specified —') }}</option>
                    @foreach ($leadSources as $src)
                        <option value="{{ $src }}" @selected($leadCurrent === $src)>{{ __($src) }}</option>
                    @endforeach
                    {{-- Sentinel: ClientRequest::prepareForValidation() swaps in
                         the typed value, and the controller adds it to the
                         company's list so it's there next time. --}}
                    <option value="__new__">{{ __('＋ Add a new source…') }}</option>
                </select>
                {{-- Inline display:none, not just x-cloak: there's no
                     [x-cloak] rule in the compiled bundle, so the field would
                     flash on load. Alpine clears it when x-show turns true. --}}
                <div x-show="adding" x-cloak style="display:none" class="mt-2">
                    <input type="text" name="lead_source_custom" maxlength="100"
                        placeholder="{{ __('e.g. Pakistan Auto Show (PAPS) 2026') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <span class="block text-xs text-gray-500 mt-1">
                        {{ __('Saved to your list — you can pick it again for other clients.') }}
                    </span>
                </div>
                <x-input-error :messages="$errors->get('lead_source')" class="mt-1" />
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center gap-2 mb-3">
            <h3 class="font-semibold text-gray-900">{{ __('Internal notes') }}</h3>
            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                <i class="ri-lock-2-line"></i> {{ __('Never in PDFs or emails') }}
            </span>
        </div>
        <textarea name="internal_notes" rows="4"
            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800"
            placeholder="{{ __('Any notes about this client — preferences, history, context. Only visible to your team.') }}">{{ old('internal_notes', $client->internal_notes) }}</textarea>
        <x-input-error :messages="$errors->get('internal_notes')" class="mt-1" />
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ $isEdit ? route('clients.show', $client->_id) : route('clients.index') }}"
            class="text-sm text-gray-600 hover:text-gray-900">
            {{ __('Cancel') }}
        </a>
        <div class="flex items-center gap-2">
            @if ($isEdit)
                <button type="submit" form="delete-client-form"
                    class="px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg">
                    {{ __('Delete client') }}
                </button>
            @endif
            <button type="submit"
                class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                <i class="ri-check-line"></i>
                {{ $isEdit ? __('Save changes') : __('Create client') }}
            </button>
        </div>
    </div>
</form>

@if ($isEdit)
    <form id="delete-client-form" action="{{ route('clients.destroy', $client->_id) }}" method="POST" class="hidden"
        data-confirm="{{ __('Delete this client?') }}"
        data-confirm-text="{{ __('This cannot be undone.') }}"
        data-confirm-danger="1">
        @csrf
        @method('DELETE')
    </form>
    @if ($errors->has('delete'))
        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            {{ $errors->first('delete') }}
        </div>
    @endif
@endif
