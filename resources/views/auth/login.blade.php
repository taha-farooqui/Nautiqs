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
                <div class="relative">
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        placeholder="Enter password"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400 pr-11" />
                    {{-- Show/hide toggle. Vanilla JS + inline SVG because the guest
                         layout loads neither Alpine nor the Remixicon font. --}}
                    <button type="button" tabindex="-1"
                        aria-label="{{ __('Show password') }}"
                        onclick="(function(b){var i=document.getElementById('password');var hidden=i.type==='password';i.type=hidden?'text':'password';b.querySelector('.eye-show').classList.toggle('hidden',hidden);b.querySelector('.eye-hide').classList.toggle('hidden',!hidden);})(this)"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                        <svg class="eye-show w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg class="eye-hide w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.774 3.162 10.066 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
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
