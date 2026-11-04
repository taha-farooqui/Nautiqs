{{-- Primary button, like the app's Large primary: navy #0e4f79, white 16 px medium,
     8 px radius, 48 px tall. Bulletproof: the link itself carries the padding. --}}
@props(['href'])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center" bgcolor="#0e4f79" style="border-radius:8px; background:#0e4f79;">
            <a href="{{ $href }}" target="_blank"
                style="display:inline-block; padding:13px 24px; font-family:'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:16px; line-height:22px; font-weight:500; color:#ffffff; text-decoration:none; border-radius:8px;">{{ $slot }}&nbsp;&nbsp;→</a>
        </td>
    </tr>
</table>
