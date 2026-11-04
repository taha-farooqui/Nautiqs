{{-- Primary button, the app's primary: navy #0e4f79, white 15 px medium, 8 px
     radius, 44 px tall. Short labels ("Reset password"). Bulletproof: the
     link itself carries the padding. --}}
@props(['href'])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 24px;">
    <tr>
        <td align="center" bgcolor="#0e4f79" style="border-radius:8px; background:#0e4f79;">
            <a href="{{ $href }}" target="_blank"
                style="display:inline-block; padding:11px 22px; font-family:'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:15px; line-height:22px; font-weight:500; color:#ffffff; text-decoration:none; border-radius:8px; white-space:nowrap;">{{ $slot }}</a>
        </td>
    </tr>
</table>
