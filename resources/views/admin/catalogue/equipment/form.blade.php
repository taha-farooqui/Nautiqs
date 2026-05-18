@php
    $editing = isset($item->_id) && $item->_id;
    $action  = $editing ? route('admin.equipment.update', $item->_id) : route('admin.equipment.store');
@endphp
<x-admin-layout
    :title="$editing ? __('Edit equipment') : __('Add equipment')"
    :header="$editing ? $item->label : __('Add equipment')">

    <div class="mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.equipment.index') }}" class="text-gray-500 hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to equipment') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            <i class="ri-error-warning-line"></i> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl space-y-4">
        @csrf
        @if ($editing) @method('PATCH') @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Category') }} <span class="text-red-500">*</span></label>
            <select name="category" required
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected(old('category', $item->category ?? '') === $key)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Label') }} <span class="text-red-500">*</span></label>
            <input type="text" name="label" required value="{{ old('label', $item->label ?? '') }}"
                placeholder="{{ __('e.g. Bimini top, GPS chartplotter…') }}"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('admin.equipment.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button type="submit"
                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $editing ? __('Save changes') : __('Create equipment') }}
            </button>
        </div>
    </form>
</x-admin-layout>
