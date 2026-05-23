<x-admin-layout :title="__('Add dealer')" :header="__('Add a dealer')">

    <div class="mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.dealers.index') }}" class="text-gray-500 hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to dealers') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            <i class="ri-error-warning-line"></i> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.dealers.store') }}"
        class="bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl space-y-6">
        @csrf

        {{-- Dealership --}}
        <div>
            <h2 class="font-semibold text-gray-900 mb-1">{{ __('Dealership') }}</h2>
            <p class="text-xs text-gray-500 mb-3">{{ __('The company that will use Nautiqs to send quotes.') }}</p>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Company name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="company_name" required maxlength="150" value="{{ old('company_name') }}"
                placeholder="e.g. Marseille Marine SAS"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
        </div>

        {{-- Admin user --}}
        <div class="pt-4 border-t border-gray-100">
            <h2 class="font-semibold text-gray-900 mb-1">{{ __('First admin user') }}</h2>
            <p class="text-xs text-gray-500 mb-3">{{ __('This user can log in immediately and invite their team afterwards.') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="admin_name" required maxlength="255" value="{{ old('admin_name') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }} <span class="text-red-500">*</span></label>
                    <input type="email" name="admin_email" required maxlength="255" value="{{ old('admin_email') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Password') }} <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <p class="text-[11px] text-gray-500 mt-1">{{ __('At least 8 characters. Share securely with the dealer.') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Confirm password') }} <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100">
            <a href="{{ route('admin.dealers.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button type="submit"
                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-add-line"></i> {{ __('Create dealer') }}
            </button>
        </div>
    </form>
</x-admin-layout>
