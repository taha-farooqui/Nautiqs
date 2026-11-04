@php
    $roleLabel = match ($invite->role) {
        \App\Models\User::ROLE_TENANT_ADMIN  => __('Admin'),
        \App\Models\User::ROLE_BRAND_MANAGER => __('Brand manager'),
        default                              => __('Salesperson'),
    };
@endphp
<x-email.layout :title="__('Join :company on Nautiqs', ['company' => $companyName])"
    :preheader="__(':inviter has invited you to join :company on Nautiqs.', ['inviter' => $invite->invited_by_name, 'company' => $companyName])"
    :footer-note="__('If you did not expect this email, you can safely ignore it.')">
    <x-email.text :strong="true">{{ __('Hello :name,', ['name' => $invite->name]) }}</x-email.text>
    <x-email.text>{{ __(':inviter has invited you to join :company on Nautiqs as :role.', ['inviter' => $invite->invited_by_name, 'company' => $companyName, 'role' => $roleLabel]) }}
        {{ __('Click the button below to set your password and access the workspace.') }}</x-email.text>
    <x-email.panel :rows="[__('Workspace') => $companyName, __('Role') => $roleLabel, __('Email') => $invite->email]" />
    <x-email.button :href="$acceptUrl">{{ __('Accept invitation') }}</x-email.button>
    @if ($invite->expires_at)
        <x-email.note>{{ __('This invitation expires on :date.', ['date' => $invite->expires_at->translatedFormat('j F Y')]) }}</x-email.note>
    @endif
    <x-email.fallback-link :url="$acceptUrl" />
</x-email.layout>
