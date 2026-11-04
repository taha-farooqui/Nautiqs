<x-guest-layout :title="__('Confirm password') . ' · Nautiqs'">
    <x-auth.heading :title="__('Confirm your password')">{{ __('This is a secure area. Please confirm your password before continuing.') }}</x-auth.heading>

    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-6">
        @csrf
        <x-auth.field :label="__('Password')" name="password" type="password"
            :placeholder="__('Your password')" required autofocus autocomplete="current-password" />
        <x-auth.button>{{ __('Confirm') }}</x-auth.button>
    </form>
</x-guest-layout>
