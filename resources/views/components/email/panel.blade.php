{{-- Grey info panel (#f7f7f8, #e8e8eb border, 8 px radius). Pass rows as
     [label => value]; or use the slot for free content. --}}
@props(['rows' => []])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
    style="margin:0 0 20px; background:#f7f7f8; border:1px solid #e8e8eb; border-radius:8px;">
    @foreach ($rows as $label => $value)
        <tr>
            <td style="padding:{{ $loop->first ? '12px' : '4px' }} 16px {{ $loop->last ? '12px' : '4px' }}; font-size:12px; line-height:20px; color:#73737c; width:38%; vertical-align:top;">{{ $label }}</td>
            <td style="padding:{{ $loop->first ? '12px' : '4px' }} 16px {{ $loop->last ? '12px' : '4px' }}; font-size:14px; line-height:22px; font-weight:500; color:#0b0b0d; vertical-align:top;">{{ $value }}</td>
        </tr>
    @endforeach
    @if ($slot->isNotEmpty())
        <tr><td colspan="2" style="padding:12px 16px; font-size:14px; line-height:22px; color:#55555e;">{{ $slot }}</td></tr>
    @endif
</table>
