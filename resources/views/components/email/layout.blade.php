{{--
    Nautiqs system email (password reset, verification, welcome, invitations,
    brand notifications). Same look as the app: Geist, #f7f7f8 page, white card
    with a #e8e8eb border and 12 px radius, navy primary buttons.
    Tables + inline styles only, so Gmail / Outlook / Apple Mail render it alike.
    Dealer → client emails (quotes, follow-ups, order confirmations) do NOT use
    this: they carry the dealership's own branding.
--}}
@props([
    'title' => null,          // big heading inside the card
    'preheader' => null,      // inbox preview text
    'footerNote' => null,     // "you received this because…" line under the card
])
@php
    $font = "'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title ?? 'Nautiqs' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        a { color: #0e4f79; }
        @media only screen and (max-width: 600px) {
            .nq-card { padding: 28px 22px !important; }
            .nq-title { font-size: 22px !important; line-height: 30px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f7f7f8; font-family:{{ $font }}; color:#0b0b0d;">
    @if ($preheader)
        <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f7f7f8; opacity:0;">{{ $preheader }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f7f8;">
        <tr>
            <td align="center" style="padding:48px 16px 40px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    {{-- Logo, centred above the card --}}
                    <tr>
                        <td align="center" style="padding:0 0 28px;">
                            <a href="{{ url('/') }}" style="text-decoration:none; display:inline-block;">
                                <img src="{{ asset('images/email-logo.png') }}" width="126" height="26" alt="Nautiqs"
                                    style="display:block; width:126px; height:26px; border:0; outline:none;">
                            </a>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td class="nq-card" style="background:#ffffff; border:1px solid #e8e8eb; border-radius:12px; padding:36px 40px; font-family:{{ $font }}; font-size:14px; line-height:22px; color:#55555e;">
                            @if ($title)
                                <h1 class="nq-title" style="margin:0 0 12px; font-family:{{ $font }}; font-size:22px; line-height:30px; font-weight:600; letter-spacing:-0.22px; color:#0b0b0d;">{{ $title }}</h1>
                            @endif
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer, centred --}}
                    <tr>
                        <td align="center" style="padding:24px 24px 0; font-family:{{ $font }}; font-size:12px; line-height:20px; color:#73737c; text-align:center;">
                            @if ($footerNote)
                                <p style="margin:0 0 8px;">{{ $footerNote }}</p>
                            @endif
                            <p style="margin:0;">
                                © {{ date('Y') }} Nautiqs
                                @if (config('mail.from.address'))
                                    &nbsp;·&nbsp; <a href="mailto:{{ config('mail.from.address') }}" style="color:#73737c; text-decoration:underline;">{{ __('Help') }}</a>
                                @endif
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
