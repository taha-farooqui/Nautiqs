{{-- Figma "01 / Connexion / Light". --}}
<x-guest-layout :title="__('Log in') . ' · Nautiqs'">
    <x-auth.heading :title="__('Log in to Nautiqs')">{{ __('Access your brand or wholesaler workspace.') }}</x-auth.heading>

    @if (session('status'))
        <x-auth.alert>{{ session('status') }}</x-auth.alert>
    @endif

    @if (session('unverified_email') && $errors->has('email'))
        <x-auth.alert type="warning" :title="__('Email not verified')">
            {{ __('Please verify your email to log in. We sent a link to') }} <span class="font-medium">{{ session('unverified_email') }}</span>.
            <form method="POST" action="{{ route('verification.resend') }}" class="mt-1.5">
                @csrf
                <button type="submit" class="font-medium underline hover:no-underline">{{ __('Resend verification email') }}</button>
            </form>
        </x-auth.alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-6">
        @csrf

        <div class="flex flex-col gap-5">
            <x-auth.field :label="__('Work email')" name="email" type="email" :value="old('email')"
                :placeholder="__('name@company.com')" required autofocus autocomplete="username" />
            <x-auth.field :label="__('Password')" name="password" type="password"
                :placeholder="__('Your password')" required autocomplete="current-password" />

            <label for="remember_me" class="flex items-center gap-2.5 cursor-pointer">
                <input id="remember_me" type="checkbox" name="remember" value="1" @checked(old('remember', true))
                    class="w-[18px] h-[18px] rounded-[5px] border-nq-border-strong text-primary-800 focus:ring-[3px] focus:ring-primary-800/15 focus:ring-offset-0" />
                <span class="text-[13px] leading-[22px] text-nq-text-2">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-col gap-4">
            <x-auth.button>{{ __('Sign in') }}</x-auth.button>
            @if (Route::has('password.request'))
                <x-auth.link :href="route('password.request')">{{ __('Forgot your password?') }}</x-auth.link>
            @endif
        </div>
    </form>

    <x-auth.link :href="route('account-request.create')">{{ __('Your company uses Nautiqs? Request access') }}</x-auth.link>
</x-guest-layout>
