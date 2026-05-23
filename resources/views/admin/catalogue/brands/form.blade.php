@php
    $editing = isset($brand->_id) && $brand->_id;
    $action  = $editing ? route('admin.brands.update', $brand->_id) : route('admin.brands.store');
@endphp
<x-admin-layout
    :title="$editing ? __('Edit brand') : __('Add brand')"
    :header="$editing ? $brand->name : __('Add brand')">

    <div class="mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.brands.index') }}" class="text-gray-500 hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to brands') }}
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
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" required value="{{ old('name', $brand->name ?? '') }}"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('admin.brands.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button type="submit"
                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $editing ? __('Save changes') : __('Create brand') }}
            </button>
        </div>
    </form>
</x-admin-layout>
