<x-admin-layout :title="__('New brand account')" :header="__('New brand account')">
    <div class="mb-4 text-sm">
        <a href="{{ route('admin.brand-accounts.index') }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Brand accounts') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.brand-accounts.store') }}" class="max-w-3xl space-y-4"
        x-data="{ kind: @js(old('kind', 'boat')) }">
        @csrf
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account name') }} <span class="text-red-500">*</span></label>
                <input id="name" name="name" required maxlength="160" value="{{ old('name') }}" placeholder="{{ __('e.g. Atlantic Marine Import') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700 mb-2">{{ __('Type') }} <span class="text-red-500">*</span></span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-lg">
                    <label class="flex items-start gap-2.5 rounded-lg border p-3 cursor-pointer has-[:checked]:border-primary-800 has-[:checked]:bg-primary-50 border-gray-200">
                        <input type="radio" name="kind" value="boat" x-model="kind" class="mt-1 text-primary-800 focus:ring-primary-800">
                        <span><span class="block text-sm font-medium text-gray-900">{{ __('Boat brands') }}</span><span class="block text-xs text-gray-500">{{ __('Models, versions, options') }}</span></span>
                    </label>
                    <label class="flex items-start gap-2.5 rounded-lg border p-3 cursor-pointer has-[:checked]:border-primary-800 has-[:checked]:bg-primary-50 border-gray-200">
                        <input type="radio" name="kind" value="engine" x-model="kind" class="mt-1 text-primary-800 focus:ring-primary-800">
                        <span><span class="block text-sm font-medium text-gray-900">{{ __('Engine brand') }}</span><span class="block text-xs text-gray-500">{{ __('Engines, kits, propellers') }}</span></span>
                    </label>
                </div>
            </div>

            <div>
                <label for="brands" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brands it manages') }} <span class="text-red-500">*</span></label>
                <textarea id="brands" name="brands" rows="3" required class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800"
                    placeholder="{{ __('One per line, or separated by commas') }}">{{ old('brands') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">{{ __('One brand for a manufacturer, several for an importer. A brand can belong to one account only.') }}</p>
                <x-input-error :messages="$errors->get('brands')" class="mt-1" />
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">{{ __('Brand manager') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="manager_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                    <input id="manager_name" name="manager_name" required maxlength="120" value="{{ old('manager_name') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <x-input-error :messages="$errors->get('manager_name')" class="mt-1" />
                </div>
                <div>
                    <label for="manager_email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }} <span class="text-red-500">*</span></label>
                    <input id="manager_email" name="manager_email" type="email" required maxlength="200" value="{{ old('manager_email') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <x-input-error :messages="$errors->get('manager_email')" class="mt-1" />
                </div>
            </div>
            <p class="text-sm text-gray-600">{{ __('They receive an email to set a password, then land on their brand portal.') }}</p>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.brand-accounts.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-send-plane-line"></i> {{ __('Create & send invitation') }}
            </button>
        </div>
    </form>
</x-admin-layout>
