{{-- Figma "04 / Accepter une invitation / Light". --}}
<x-guest-layout :title="__('Accept the invitation') . ' · Nautiqs'">
    <x-auth.heading :title="__('Join your team')">
        {{ __(':name invited you to join their workspace as :role.', [
            'name' => $invite->invited_by_name,
            'role' => $invite->role === \App\Models\User::ROLE_TENANT_ADMIN ? __('Admin') : __('Salesperson'),
        ]) }}
    </x-auth.heading>

    <form method="POST" action="{{ route('invitations.accept.store', $invite->token) }}" class="flex flex-col gap-6">
        @csrf

        <div class="flex flex-col gap-5">
            <x-auth.field :label="__('Full name')" name="invite_name" :value="$invite->name" disabled />
            <x-auth.field :label="__('Work email')" name="invite_email" type="email" :value="$invite->email" disabled />
            <x-auth.field :label="__('Password')" name="password" type="password"
                :placeholder="__('At least 8 characters')" required minlength="8" autofocus autocomplete="new-password" />
            <x-auth.field :label="__('Confirm password')" name="password_confirmation" type="password"
                :placeholder="__('Type it again')" required minlength="8" autocomplete="new-password" />
        </div>

        <x-auth.button>{{ __('Create my account') }}</x-auth.button>
    </form>

    <x-auth.link :href="route('login')">{{ __('Already have an account? Log in') }}</x-auth.link>
</x-guest-layout>
