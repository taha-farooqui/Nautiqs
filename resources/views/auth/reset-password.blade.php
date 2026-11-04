{{-- Figma "09 / Nouveau mot de passe / Light". On success Laravel goes back to the
     login screen with a status ("10 / Mot de passe modifié"). --}}
<x-guest-layout :title="__('Reset password') . ' · Nautiqs'">
    <x-auth.heading :title="__('Choose a new password')">{{ __('Pick something strong you will remember.') }}</x-auth.heading>

    <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-6">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="flex flex-col gap-5">
            <x-auth.field :label="__('Work email')" name="email" type="email" :value="old('email', $request->email)"
                required autocomplete="username" />
            <x-auth.field :label="__('New password')" name="password" type="password"
                :placeholder="__('At least 8 characters')" required autofocus autocomplete="new-password" />
            <x-auth.field :label="__('Confirm password')" name="password_confirmation" type="password"
                :placeholder="__('Type it again')" required autocomplete="new-password" />
        </div>

        <x-auth.button>{{ __('Save my password') }}</x-auth.button>
    </form>

    <x-auth.link :href="route('login')">{{ __('Back to log in') }}</x-auth.link>
</x-guest-layout>
