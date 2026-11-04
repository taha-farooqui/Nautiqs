{{-- Figma "07 / Mot de passe oublié / Light"; once sent, "08 / Lien de réinitialisation envoyé". --}}
<x-guest-layout :title="__('Forgot password') . ' · Nautiqs'">
    @if (session('status'))
        <x-auth.heading :title="__('Check your inbox')">{{ session('status') }}</x-auth.heading>
        <div class="flex flex-col gap-4">
            <x-auth.button :href="route('login')" variant="secondary">{{ __('Back to log in') }}</x-auth.button>
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <input type="hidden" name="email" value="{{ old('email') }}">
                <x-auth.link>{{ __('Resend the link') }}</x-auth.link>
            </form>
        </div>
    @else
        <x-auth.heading :title="__('Forgot your password?')">{{ __('Enter your email and we will send you a link to reset it.') }}</x-auth.heading>

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf
            <x-auth.field :label="__('Work email')" name="email" type="email" :value="old('email')"
                :placeholder="__('name@company.com')" required autofocus autocomplete="email" />
            <x-auth.button>{{ __('Send the reset link') }}</x-auth.button>
        </form>

        <x-auth.link :href="route('login')">{{ __('Back to log in') }}</x-auth.link>
    @endif
</x-guest-layout>
