<x-guest-layout title="Confirm password · Nautiqs">
    <div class="max-w-md">
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Confirm your password</h1>
        <p class="text-gray-500 mb-8">
            This is a secure area. Please confirm your password before continuing.
        </p>

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
            @csrf

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Password <span class="text-red-500">*</span>
                </label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    placeholder="Enter your password"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <button type="submit"
                class="w-full bg-primary-800 hover:bg-primary-900 text-white font-semibold py-3 rounded-lg transition">
                Confirm
            </button>
        </form>
    </div>
</x-guest-layout>
