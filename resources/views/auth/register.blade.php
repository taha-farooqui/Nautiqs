<x-guest-layout title="Sign up · Nautiqs">
    <div class="max-w-md">
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Create your account</h1>
        <p class="text-gray-500 mb-8">Start building quotes in under 2 minutes.</p>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                    Full name <span class="text-red-500">*</span>
                </label>
                <input id="name" name="name" type="text" required autofocus autocomplete="name"
                    value="{{ old('name') }}"
                    placeholder="Your full name"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    Email <span class="text-red-500">*</span>
                </label>
                <input id="email" name="email" type="email" required autocomplete="username"
                    value="{{ old('email') }}"
                    placeholder="Enter your email address"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Password <span class="text-red-500">*</span>
                </label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    placeholder="Create a password"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                    Confirm password <span class="text-red-500">*</span>
                </label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    placeholder="Re-enter password"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <button type="submit"
                class="w-full bg-primary-800 hover:bg-primary-900 text-white font-semibold py-3 rounded-lg transition">
                Create account
            </button>
        </form>

        <div class="flex items-center gap-4 my-6">
            <div class="h-px bg-gray-200 flex-1"></div>
            <span class="text-sm text-gray-400">Or, sign up with</span>
            <div class="h-px bg-gray-200 flex-1"></div>
        </div>

        <a href="{{ route('auth.google.redirect') }}"
            class="w-full inline-flex items-center justify-center gap-3 border border-gray-300 hover:bg-gray-50 py-3 rounded-lg font-medium text-gray-700 transition">
            <svg class="w-5 h-5" viewBox="0 0 24 24">
                <path fill="#EA4335" d="M12 10.2v3.9h5.48c-.24 1.3-.97 2.4-2.06 3.13v2.6h3.33c1.95-1.8 3.07-4.45 3.07-7.62 0-.7-.06-1.38-.18-2.01H12z"/>
                <path fill="#34A853" d="M12 22c2.78 0 5.11-.92 6.82-2.48l-3.33-2.6c-.92.62-2.11.99-3.49.99-2.68 0-4.95-1.81-5.76-4.24H2.8v2.66C4.5 19.73 7.99 22 12 22z"/>
                <path fill="#FBBC05" d="M6.24 13.67A6 6 0 016 12c0-.58.1-1.14.24-1.67V7.67H2.8A10 10 0 002 12c0 1.62.39 3.15 1.08 4.5l3.16-2.83z"/>
                <path fill="#4285F4" d="M12 5.8c1.52 0 2.88.52 3.95 1.54l2.96-2.96C17.11 2.78 14.78 2 12 2 7.99 2 4.5 4.27 2.8 7.67l3.44 2.66C7.05 7.6 9.32 5.8 12 5.8z"/>
            </svg>
            Continue with Google
        </a>

        <p class="text-center text-sm text-gray-500 mt-6">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-primary-800 hover:text-primary-900">
                Log in
            </a>
        </p>
    </div>
</x-guest-layout>
