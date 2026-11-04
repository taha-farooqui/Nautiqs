{{-- Figma "05 / Vérifier son e-mail / Light", kept link-based: the email holds a
     verification link (the 6-digit code variant is not built). --}}
<x-guest-layout :title="__('Verify email') . ' · Nautiqs'">
    <x-auth.heading :title="__('Verify your email')">
        {{ __('We sent a verification link to') }} <span class="text-nq-text">{{ auth()->user()?->email }}</span>.
        {{ __('Click it to activate your account.') }}
    </x-auth.heading>

    @if (session('status') == 'verification-link-sent')
        <x-auth.alert>{{ __('A new verification link has been sent to your email.') }}</x-auth.alert>
    @endif

    <div class="flex flex-col gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-auth.button>{{ __('Resend the email') }}</x-auth.button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-auth.link>{{ __('Use another email address') }}</x-auth.link>
        </form>
    </div>
</x-guest-layout>
