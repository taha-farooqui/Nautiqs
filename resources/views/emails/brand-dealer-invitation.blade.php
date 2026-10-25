<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Invitation') }}</title>
</head>
<body style="font-family: Inter, Arial, sans-serif; color: #1f2937; background: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">
        <div style="padding: 22px 28px; background: #0e4f79; color: #ffffff;">
            <span style="font-size: 20px; font-weight: 700;">Nautiqs</span>
        </div>

        <div style="padding: 28px; font-size: 14px; line-height: 1.6;">
            <p style="margin: 0 0 16px;">{{ __('Hello,') }}</p>
            <p style="margin: 0 0 16px;">
                {{ __(':brand invites you to receive its official catalogue on Nautiqs.', ['brand' => $account->name]) }}
            </p>

            <div style="margin: 0 0 16px; padding: 14px 16px; background: #f1f6fa; border-radius: 8px;">
                <p style="margin: 0 0 6px;"><strong>{{ __('Brands') }}:</strong> {{ implode(', ', $brandNames) }}</p>
                <p style="margin: 0 0 6px;"><strong>{{ __('Your price level') }}:</strong> {{ $levelName }} — {{ __('your purchase prices are filled in for you') }}</p>
                <p style="margin: 0;"><strong>{{ __('Always up to date') }}:</strong> {{ __('price changes reach you automatically') }}</p>
            </div>

            @if ($access->message)
                <p style="margin: 0 0 16px; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-style: italic; color: #374151;">
                    “{{ $access->message }}” — {{ $access->invited_by_name }}
                </p>
            @endif

            <p style="margin: 0 0 20px; color: #4b5563;">
                {{ __('If you accept, :brand will see statistics on the quotes you make with its products (models, amounts, discounts, status). Your clients\' personal details are never shared.', ['brand' => $account->name]) }}
            </p>

            <p style="margin: 0 0 20px; text-align: center;">
                <a href="{{ $ctaUrl }}" style="display: inline-block; padding: 12px 28px; background: #0e4f79; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
                    {{ $hasAccount ? __('Review the invitation') : __('Request my Nautiqs account') }}
                </a>
            </p>
            @unless ($hasAccount)
                <p style="margin: 0 0 16px; font-size: 13px; color: #6b7280;">{{ __('Use this email address when you request your account: the invitation will be waiting for you once it is open.') }}</p>
            @endunless

            <p style="margin: 0; font-size: 12px; color: #9ca3af;">{{ __('You can decline, or disconnect later from Partner brands in your settings.') }}</p>
        </div>
    </div>
</body>
</html>
