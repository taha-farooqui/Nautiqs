<x-guest-layout title="Reset password · Nautiqs">
    <div class="max-w-md">
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Choose a new password</h1>
        <p class="text-gray-500 mb-8">Pick something strong you'll remember.</p>

        <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    Email <span class="text-red-500">*</span>
                </label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username"
                    value="{{ old('email', $request->email) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    New password <span class="text-red-500">*</span>
                </label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    placeholder="Enter new password"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                    Confirm password <span class="text-red-500">*</span>
                </label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    placeholder="Re-enter new password"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <button type="submit"
                class="w-full bg-primary-800 hover:bg-primary-900 text-white font-semibold py-3 rounded-lg transition">
                Reset password
            </button>
        </form>
    </div>
</x-guest-layout>
