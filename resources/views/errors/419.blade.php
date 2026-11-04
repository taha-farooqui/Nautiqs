{{-- Figma "14 / Session expirée / Light": shown when a form is sent after the
     session (or its CSRF token) has expired. --}}
<x-guest-layout :title="__('Session expired') . ' · Nautiqs'">
    <x-auth.heading :title="__('Your session has expired')">{{ __('For your security, you were logged out after a period of inactivity. Log in again to continue.') }}</x-auth.heading>
    <x-auth.button :href="route('login')">{{ __('Log in again') }}</x-auth.button>
</x-guest-layout>
