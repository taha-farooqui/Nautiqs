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
        <h3 class="font-semibold text-gray-900 mb-4">Contact</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    First name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="first_name" value="{{ old('first_name', $client->first_name) }}"
                    required class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Last name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="last_name" value="{{ old('last_name', $client->last_name) }}"
                    required class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Company name <span class="text-gray-400 text-xs font-normal">(optional — if client is a business)</span></label>
                <input type="text" name="company_name" value="{{ old('company_name', $client->company_name) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('company_name')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $client->email) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="tel" name="phone" value="{{ old('phone', $client->phone) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Address</h3>
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Street address</label>
                <input type="text" name="address_line" value="{{ old('address_line', $client->address_line) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('address_line')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Postal code</label>
                <input type="text" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('postal_code')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <input type="text" name="city" value="{{ old('city', $client->city) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('city')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                <input type="text" name="country" value="{{ old('country', $client->country ?? 'France') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('country')" class="mt-1" />
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center gap-2 mb-3">
            <h3 class="font-semibold text-gray-900">Internal notes</h3>
            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                <i class="ri-lock-2-line"></i> Never in PDFs or emails
            </span>
        </div>
        <textarea name="internal_notes" rows="4"
            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800"
            placeholder="Any notes about this client — preferences, history, context. Only visible to your team.">{{ old('internal_notes', $client->internal_notes) }}</textarea>
        <x-input-error :messages="$errors->get('internal_notes')" class="mt-1" />
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ $isEdit ? route('clients.show', $client->_id) : route('clients.index') }}"
            class="text-sm text-gray-600 hover:text-gray-900">
            Cancel
        </a>
        <div class="flex items-center gap-2">
            @if ($isEdit)
                <button type="button" onclick="if(confirm('Delete this client? This cannot be undone.')) document.getElementById('delete-client-form').submit();"
                    class="px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg">
                    Delete client
                </button>
            @endif
            <button type="submit"
                class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                <i class="ri-check-line"></i>
                {{ $isEdit ? 'Save changes' : 'Create client' }}
            </button>
        </div>
    </div>
</form>

@if ($isEdit)
    <form id="delete-client-form" action="{{ route('clients.destroy', $client->_id) }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
    @if ($errors->has('delete'))
        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            {{ $errors->first('delete') }}
        </div>
    @endif
@endif
