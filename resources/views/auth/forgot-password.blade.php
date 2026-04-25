<x-guest-layout title="Forgot password · Nautiqs">
    <div class="max-w-md">
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Forgot your password?</h1>
        <p class="text-gray-500 mb-8">
            No problem. Enter your email and we'll send you a link to reset it.
        </p>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    Email <span class="text-red-500">*</span>
                </label>
                <input id="email" name="email" type="email" required autofocus
                    value="{{ old('email') }}"
                    placeholder="Enter your email address"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <button type="submit"
                class="w-full bg-primary-800 hover:bg-primary-900 text-white font-semibold py-3 rounded-lg transition">
                Email password reset link
            </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-6">
            Remembered it?
            <a href="{{ route('login') }}" class="font-semibold text-primary-800 hover:text-primary-900">
                Back to log in
            </a>
        </p>
    </div>
</x-guest-layout>
