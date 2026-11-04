<x-email.layout :title="__('Verify your email')" :preheader="__('Confirm your email address to activate your Nautiqs account.')"
    :footer-note="__('You are receiving this email because an account was created on Nautiqs with :email.', ['email' => $email])">
    <x-email.text>{{ __('Confirm that :email is your email address to activate your Nautiqs account.', ['email' => $email]) }}</x-email.text>
    <x-email.button :href="$url">{{ __('Verify email') }}</x-email.button>
    <x-email.note>{{ __('This link expires in :count minutes.', ['count' => $expire]) }}</x-email.note>
    <x-email.fallback-link :url="$url" />
</x-email.layout>
