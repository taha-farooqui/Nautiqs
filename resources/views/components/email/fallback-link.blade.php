{{-- "Button not working?" raw link, under a hairline divider. --}}
@props(['url'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0; border-top:1px solid #f0f0f2;">
    <tr>
        <td style="padding:16px 0 0; font-size:12px; line-height:20px; color:#73737c;">
            {{ __('If the button does not work, copy this link into your browser:') }}<br>
            <a href="{{ $url }}" style="color:#0e4f79; word-break:break-all;">{{ $url }}</a>
        </td>
    </tr>
</table>
