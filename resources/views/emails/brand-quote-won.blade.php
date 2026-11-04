@php
    $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
    $th = 'padding:8px 10px; font-size:12px; line-height:20px; font-weight:400; color:#55555e; background:#fbfbfb; border-bottom:1px solid #f0f0f2;';
    $td = 'padding:10px; font-size:13px; line-height:20px; color:#0b0b0d; border-bottom:1px solid #f0f0f2;';
@endphp
<x-email.layout :title="$q['boat'] . ($q['version'] ? ' · ' . $q['version'] : '')"
    :preheader="__(':dealer won a sale: :boat', ['dealer' => $q['dealer'], 'boat' => $q['boat']])"
    :footer-note="__('Client personal details, purchase prices and margins are never shared with brands.')">

    {{-- Success tag, like the app's "Gagné" tag --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;">
        <tr><td style="background:#ecfaf3; border:1px solid #d1f2e1; border-radius:6px; padding:2px 8px; font-size:12px; line-height:20px; font-weight:500; color:#0b6845;">● {{ __('Quote won') }} · {{ $account->name }}</td></tr>
    </table>

    <x-email.panel :rows="[
        __('Dealer') => $q['dealer'],
        __('Salesperson') => $q['salesperson'] ?: '—',
        __('Quote') => $q['number'] . ' · ' . __('made :date', ['date' => $q['date']?->format('d/m/Y')]),
        __('Won') => $q['won_at']?->format('d/m/Y'),
        __('Client') => $q['client'],
    ]" />

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px; border:1px solid #e8e8eb; border-radius:8px; border-collapse:separate; overflow:hidden;">
        <tr>
            <th align="left" style="{{ $th }}">{{ __('Item') }}</th>
            <th align="center" style="{{ $th }}">{{ __('Qty') }}</th>
            <th align="right" style="{{ $th }}">{{ __('Public HT') }}</th>
            <th align="right" style="{{ $th }}">{{ __('Discount') }}</th>
            <th align="right" style="{{ $th }}">{{ __('Line HT') }}</th>
        </tr>
        @foreach ($q['lines'] as $l)
            <tr>
                <td style="{{ $td }} {{ $l['kind'] === 'boat' ? 'font-weight:500;' : '' }}">{{ $l['label'] }}</td>
                <td align="center" style="{{ $td }}">{{ $l['qty'] }}</td>
                <td align="right" style="{{ $td }} white-space:nowrap;">{{ $eur($l['unit']) }}</td>
                <td align="right" style="{{ $td }} color:{{ $l['discount_pct'] > 0 ? '#a02c30' : '#73737c' }};">{{ $l['discount_pct'] > 0 ? '−' . $l['discount_pct'] . ' %' : '—' }}</td>
                <td align="right" style="{{ $td }} font-weight:500; white-space:nowrap;">{{ $eur($l['total']) }}</td>
            </tr>
        @endforeach
        @if ($q['other_count'])
            <tr>
                <td colspan="4" style="{{ $td }} color:#55555e;">{{ trans_choice('{1}Other item (other brands or services)|[2,*]Other items (other brands or services): :count', $q['other_count'], ['count' => $q['other_count']]) }}</td>
                <td align="right" style="{{ $td }} white-space:nowrap;">{{ $eur($q['other_total']) }}</td>
            </tr>
        @endif
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;">
        <tr><td align="right" style="padding:4px 10px; font-size:13px; color:#55555e;">{{ __('Your products HT') }}</td><td align="right" style="padding:4px 10px; width:130px; font-size:14px; font-weight:600; color:#0b0b0d; white-space:nowrap;">{{ $eur($q['value_ht']) }}</td></tr>
        @if ($q['discount_pct'] > 0)
            <tr><td align="right" style="padding:4px 10px; font-size:13px; color:#55555e;">{{ __('Discounts on your products') }}</td><td align="right" style="padding:4px 10px; font-size:13px; color:#a02c30;">−{{ $q['discount_pct'] }} %</td></tr>
        @endif
        @if ($q['global_discount_pct'] > 0)
            <tr><td align="right" style="padding:4px 10px; font-size:13px; color:#55555e;">{{ __('Quote-wide discount') }}</td><td align="right" style="padding:4px 10px; font-size:13px; color:#a02c30;">−{{ round($q['global_discount_pct'], 1) }} %</td></tr>
        @endif
        <tr><td align="right" style="padding:4px 10px; font-size:13px; color:#55555e;">{{ __('Quote total HT') }}</td><td align="right" style="padding:4px 10px; font-size:13px; color:#0b0b0d; white-space:nowrap;">{{ $eur($q['total_ht']) }}</td></tr>
    </table>

    <x-email.button :href="$url">{{ __('View quote') }}</x-email.button>
</x-email.layout>
