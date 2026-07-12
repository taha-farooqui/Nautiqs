<x-guest-layout :title="__('Request an account') . ' · Nautiqs'">
    <div class="max-w-md">
        <h1 class="text-3xl font-bold text-gray-900 mb-1">{{ __('Request an account') }}</h1>
        <p class="text-gray-500 mb-8">
            {{ __('Tell us about your dealership — we will review your request and email you when your account is ready.') }}
        </p>

        @if (session('status'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                <p class="font-medium">{{ session('status') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('account-request.store') }}" class="space-y-5">
            @csrf

            {{-- Honeypot — hidden from humans, tempting for bots. --}}
            <div style="position:absolute; left:-9999px;" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off" />
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Full name') }} <span class="text-red-500">*</span>
                </label>
                <input id="name" name="name" type="text" required autofocus
                    value="{{ old('name') }}"
                    placeholder="{{ __('Your full name') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Email') }} <span class="text-red-500">*</span>
                </label>
                <input id="email" name="email" type="email" required
                    value="{{ old('email') }}"
                    placeholder="{{ __('Enter your email address') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Dealership name') }} <span class="text-red-500">*</span>
                </label>
                <input id="company_name" name="company_name" type="text" required
                    value="{{ old('company_name') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Phone') }}
                </label>
                <input id="phone" name="phone" type="tel"
                    value="{{ old('phone') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Message') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span>
                </label>
                <textarea id="message" name="message" rows="3"
                    placeholder="{{ __('Anything we should know about your dealership?') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 placeholder:text-gray-400">{{ old('message') }}</textarea>
                <x-input-error :messages="$errors->get('message')" class="mt-2" />
            </div>

            <button type="submit"
                class="w-full bg-primary-800 hover:bg-primary-900 text-white font-semibold py-3 rounded-lg transition">
                {{ __('Send my request') }}
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
