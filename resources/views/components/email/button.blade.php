{{-- Primary button, the app's primary: navy #0e4f79, white 15 px medium, 8 px
     radius, 44 px tall. Height + line-height center the label (no vertical
     padding) so Gmail and Outlook never clip the text. --}}
@props(['href'])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 24px;">
    <tr>
        <td bgcolor="#0e4f79" style="border-radius:8px; background:#0e4f79;">
            <a href="{{ $href }}" target="_blank"
                style="display:inline-block; height:44px; line-height:44px; padding:0 24px; font-family:'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:15px; font-weight:500; color:#ffffff; text-decoration:none; border-radius:8px; background:#0e4f79; white-space:nowrap; mso-padding-alt:0;">{{ $slot }}</a>
        </td>
    </tr>
</table>
