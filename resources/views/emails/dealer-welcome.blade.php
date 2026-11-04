<x-email.layout :title="__('Welcome to Nautiqs')" :preheader="__('Your :company account is ready: set your password to get started.', ['company' => $companyName])"
    :footer-note="__('If you did not expect this email, you can safely ignore it.')">
    <x-email.text :strong="true">{{ __('Hello :name,', ['name' => $user->name]) }}</x-email.text>
    <x-email.text>{{ __('Your dealership account for :company is ready on Nautiqs. To get started, set your password using the secure link below.', ['company' => $companyName]) }}</x-email.text>
    <x-email.panel :rows="[__('Company') => $companyName, __('Email') => $user->email]" />
    <x-email.button :href="$setupUrl">{{ __('Set up your password') }}</x-email.button>
    <x-email.note>{{ __('This setup link expires in 60 minutes. If it has expired, use “Forgot password” on the login page to get a new one.') }}</x-email.note>
    <x-email.fallback-link :url="$setupUrl" />
</x-email.layout>
