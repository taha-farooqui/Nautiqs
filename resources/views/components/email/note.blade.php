{{-- Coloured note: warning (expiry), info, success. App semantic colours. --}}
@props(['type' => 'warning'])
@php
    [$bg, $border, $color] = match ($type) {
        'info'    => ['#eef7fe', '#d4e9fb', '#1d6aa5'],
        'success' => ['#ecfaf3', '#d1f2e1', '#0b6845'],
        default   => ['#fff8eb', '#fbe5bd', '#8a5a12'],
    };
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
    style="margin:0 0 20px; background:{{ $bg }}; border:1px solid {{ $border }}; border-radius:8px;">
    <tr><td style="padding:10px 14px; font-size:13px; line-height:20px; color:{{ $color }};">{{ $slot }}</td></tr>
</table>
