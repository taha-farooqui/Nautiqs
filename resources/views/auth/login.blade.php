<x-guest-layout title="Log in · Nautiqs">
    <div class="max-w-md">
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Welcome back!</h1>
        <p class="text-gray-500 mb-8">Log in to your dealership workspace.</p>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        @if (session('unverified_email') && $errors->has('email'))
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm text-amber-800 font-medium">Email not verified</p>
                <p class="text-sm text-amber-700 mt-1">
                    Please verify your email to log in. We sent a link to
                    <span class="font-semibold">{{ session('unverified_email') }}</span>.
                </p>
                <form method="POST" action="{{ route('verification.resend') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-primary-800 hover:text-primary-900 underline">
                        Resend verification email
                    </button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    Email <span class="text-red-500">*</span>
                </label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username"
                    value="{{ old('email') }}"
                    placeholder="Enter your email address"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Password <span class="text-red-500">*</span>
                </label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    placeholder="Enter password"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" name="remember"
                        class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                    <span class="ms-2 text-sm text-gray-600">Remember me</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                        class="text-sm font-medium text-primary-800 hover:text-primary-900">
                        Forgot your password?
                    </a>
                @endif
            </div>

            <button type="submit"
                class="w-full bg-primary-800 hover:bg-primary-900 text-white font-semibold py-3 rounded-lg transition">
                Log in
            </button>
        </form>
    </div>
</x-guest-layout>
