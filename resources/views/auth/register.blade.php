<x-guest-layout :title="__('Sign up') . ' · Nautiqs'">
    <div class="max-w-md">
        <h1 class="text-3xl font-bold text-gray-900 mb-1">{{ __('Create your account') }}</h1>
        <p class="text-gray-500 mb-8">{{ __('Start building quotes in under 2 minutes.') }}</p>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Full name') }} <span class="text-red-500">*</span>
                </label>
                <input id="name" name="name" type="text" required autofocus autocomplete="name"
                    value="{{ old('name') }}"
                    placeholder="{{ __('Your full name') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Email') }} <span class="text-red-500">*</span>
                </label>
                <input id="email" name="email" type="email" required autocomplete="username"
                    value="{{ old('email') }}"
                    placeholder="{{ __('Enter your email address') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Password') }} <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                        placeholder="{{ __('Create a password') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400 pr-11" />
                    {{-- Show/hide toggle. Vanilla JS + inline SVG because the guest
                         layout loads neither Alpine nor the Remixicon font. --}}
                    <button type="button" tabindex="-1"
                        aria-label="{{ __('Show password') }}"
                        onclick="(function(b,id){var i=document.getElementById(id);var h=i.type==='password';i.type=h?'text':'password';b.querySelector('.eye-show').classList.toggle('hidden',h);b.querySelector('.eye-hide').classList.toggle('hidden',!h);})(this,'password')"
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

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Confirm password') }} <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        placeholder="{{ __('Re-enter password') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400 pr-11" />
                    <button type="button" tabindex="-1"
                        aria-label="{{ __('Show password') }}"
                        onclick="(function(b,id){var i=document.getElementById(id);var h=i.type==='password';i.type=h?'text':'password';b.querySelector('.eye-show').classList.toggle('hidden',h);b.querySelector('.eye-hide').classList.toggle('hidden',!h);})(this,'password_confirmation')"
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
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <button type="submit"
                class="w-full bg-primary-800 hover:bg-primary-900 text-white font-semibold py-3 rounded-lg transition">
                {{ __('Create account') }}
            </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-6">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-semibold text-primary-800 hover:text-primary-900">
                {{ __('Log in') }}
            </a>
        </p>
    </div>
</x-guest-layout>
