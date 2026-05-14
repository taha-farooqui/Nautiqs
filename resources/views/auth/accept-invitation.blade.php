<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Welcome to Nautiqs') }}</h1>
        <p class="text-sm text-gray-600 mt-1">
            {{ __(':name invited you to join their workspace as :role.', [
                'name' => $invite->invited_by_name,
                'role' => $invite->role === \App\Models\User::ROLE_TENANT_ADMIN ? __('Admin') : __('Salesperson'),
            ]) }}
        </p>
    </div>

    <form method="POST" action="{{ route('invitations.accept.store', $invite->token) }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }}</label>
            <input type="text" value="{{ $invite->name }}" disabled
                class="w-full rounded-lg border-gray-200 bg-gray-50 text-gray-700 text-sm" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
            <input type="email" value="{{ $invite->email }}" disabled
                class="w-full rounded-lg border-gray-200 bg-gray-50 text-gray-700 text-sm" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Password') }} <span class="text-red-500">*</span></label>
            <input type="password" name="password" required minlength="8"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Confirm password') }} <span class="text-red-500">*</span></label>
            <input type="password" name="password_confirmation" required minlength="8"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
        </div>

        <div class="pt-2">
            <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2.5 rounded-lg transition">
                <i class="ri-check-line"></i> {{ __('Create account') }}
            </button>
        </div>
    </form>
</x-guest-layout>
