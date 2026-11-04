<x-email.layout :title="__('Reset your password')" :preheader="__('Choose a new password for your Nautiqs account.')"
    :footer-note="__('You are receiving this email because a password reset was requested for :email.', ['email' => $email])">
    <x-email.text>{{ __('We received a request to reset the password of your Nautiqs account. Click the button below to choose a new one.') }}</x-email.text>
    <x-email.button :href="$url">{{ __('Choose my new password') }}</x-email.button>
    <x-email.note>{{ __('This link expires in :count minutes. If you did not ask for it, you can ignore this email: your password will not change.', ['count' => $expire]) }}</x-email.note>
    <x-email.fallback-link :url="$url" />
</x-email.layout>
