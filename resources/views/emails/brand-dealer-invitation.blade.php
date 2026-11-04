<x-email.layout :title="__(':brand shares its catalogue with you', ['brand' => $account->name])"
    :preheader="__(':brand invites you to receive its official catalogue on Nautiqs.', ['brand' => $account->name])"
    :footer-note="__('You can decline, or disconnect later from Partner brands in your settings.')">
    <x-email.text :strong="true">{{ __('Hello,') }}</x-email.text>
    <x-email.text>{{ __(':brand invites you to receive its official catalogue on Nautiqs.', ['brand' => $account->name]) }}</x-email.text>

    <x-email.panel :rows="[
        __('Brands') => implode(', ', $brandNames),
        __('Your price level') => $levelName . ' — ' . __('your purchase prices are filled in for you'),
        __('Always up to date') => __('price changes reach you automatically'),
    ]" />

    @if ($access->message)
        <x-email.panel><span style="font-style:italic; color:#0b0b0d;">“{{ $access->message }}”</span><br><span style="font-size:12px; color:#73737c;">— {{ $access->invited_by_name }}</span></x-email.panel>
    @endif

    <x-email.text>{{ __('If you accept, :brand will see statistics on the quotes you make with its products (models, amounts, discounts, status). Your clients\' personal details are never shared.', ['brand' => $account->name]) }}</x-email.text>

    <x-email.button :href="$ctaUrl">{{ $hasAccount ? __('Review the invitation') : __('Request my Nautiqs account') }}</x-email.button>

    @unless ($hasAccount)
        <x-email.note type="info">{{ __('Use this email address when you request your account: the invitation will be waiting for you once it is open.') }}</x-email.note>
    @endunless
</x-email.layout>
