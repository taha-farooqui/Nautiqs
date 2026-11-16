@php
    $isEdit = $lead->exists;
    $action = $isEdit ? route('leads.update', $lead->_id) : route('leads.store');
    $field = 'w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800';
@endphp

<form method="POST" action="{{ $action }}" class="max-w-2xl flex flex-col gap-4">
    @csrf
    @if ($isEdit) @method('PATCH') @endif

    <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col gap-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('First name') }}</label>
                <input type="text" name="first_name" value="{{ old('first_name', $lead->first_name) }}" class="{{ $field }}" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Last name') }}</label>
                <input type="text" name="last_name" value="{{ old('last_name', $lead->last_name) }}" class="{{ $field }}" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Company') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></label>
            <input type="text" name="company_name" value="{{ old('company_name', $lead->company_name) }}" class="{{ $field }}" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email', $lead->email) }}" class="{{ $field }}" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                <input type="tel" name="phone" value="{{ old('phone', $lead->phone) }}" class="{{ $field }}" />
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
            <select name="status" class="{{ $field }}">
                @foreach (\App\Models\SalesLead::statusLabels() as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $lead->status ?? 'new') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Note') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></label>
            <textarea name="note" rows="3" class="{{ $field }}">{{ old('note', $lead->note) }}</textarea>
            <x-input-error :messages="$errors->get('note')" class="mt-1" />
        </div>
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ route('leads.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
        <button type="submit" class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2.5 rounded-lg transition">
            <i class="ri-check-line"></i> {{ $isEdit ? __('Save changes') : __('Add lead') }}
        </button>
    </div>
</form>
