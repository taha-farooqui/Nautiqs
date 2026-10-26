@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €'; @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Quote won') }}</title>
</head>
<body style="font-family: Inter, Arial, sans-serif; color: #1f2937; background: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">
        <div style="padding: 22px 28px; background: #0e4f79; color: #ffffff;">
            <span style="font-size: 20px; font-weight: 700;">Nautiqs</span>
            <span style="float: right; font-size: 13px; opacity: .8; padding-top: 6px;">{{ $account->name }}</span>
        </div>

        <div style="padding: 28px; font-size: 14px; line-height: 1.6;">
            <p style="margin: 0 0 6px; font-size: 13px; color: #059669; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;">🏆 {{ __('Quote won') }}</p>
            <h1 style="margin: 0 0 18px; font-size: 20px; color: #111827;">
                {{ $q['boat'] }}@if ($q['version']) <span style="font-weight: 400; color: #6b7280;">· {{ $q['version'] }}</span>@endif
            </h1>

            <table style="width: 100%; border-collapse: collapse; margin: 0 0 20px; background: #f1f6fa; border-radius: 8px;" cellpadding="0" cellspacing="0">
                <tr><td style="padding: 10px 16px 4px; color: #6b7280; width: 40%;">{{ __('Dealer') }}</td><td style="padding: 10px 16px 4px; font-weight: 600;">{{ $q['dealer'] }}</td></tr>
                <tr><td style="padding: 4px 16px; color: #6b7280;">{{ __('Salesperson') }}</td><td style="padding: 4px 16px;">{{ $q['salesperson'] ?: '—' }}</td></tr>
                <tr><td style="padding: 4px 16px; color: #6b7280;">{{ __('Quote') }}</td><td style="padding: 4px 16px;">{{ $q['number'] }} · {{ __('made :date', ['date' => $q['date']?->format('d/m/Y')]) }}</td></tr>
                <tr><td style="padding: 4px 16px; color: #6b7280;">{{ __('Won') }}</td><td style="padding: 4px 16px;">{{ $q['won_at']?->format('d/m/Y') }}</td></tr>
                <tr><td style="padding: 4px 16px 10px; color: #6b7280;">{{ __('Client') }}</td><td style="padding: 4px 16px 10px;">{{ $q['client'] }}</td></tr>
            </table>

            <table style="width: 100%; border-collapse: collapse; margin: 0 0 8px; font-size: 13px;" cellpadding="0" cellspacing="0">
                <tr style="background: #f9fafb; color: #6b7280; font-size: 11px; text-transform: uppercase;">
                    <th style="text-align: left; padding: 8px 10px;">{{ __('Item') }}</th>
                    <th style="text-align: center; padding: 8px 6px;">{{ __('Qty') }}</th>
                    <th style="text-align: right; padding: 8px 6px;">{{ __('Public HT') }}</th>
                    <th style="text-align: right; padding: 8px 6px;">{{ __('Discount') }}</th>
                    <th style="text-align: right; padding: 8px 10px;">{{ __('Line HT') }}</th>
                </tr>
                @foreach ($q['lines'] as $l)
                    <tr style="border-top: 1px solid #f3f4f6;">
                        <td style="padding: 8px 10px; {{ $l['kind'] === 'boat' ? 'font-weight: 600;' : '' }}">{{ $l['label'] }}</td>
                        <td style="padding: 8px 6px; text-align: center;">{{ $l['qty'] }}</td>
                        <td style="padding: 8px 6px; text-align: right; white-space: nowrap;">{{ $eur($l['unit']) }}</td>
                        <td style="padding: 8px 6px; text-align: right; color: {{ $l['discount_pct'] > 0 ? '#dc2626' : '#9ca3af' }};">{{ $l['discount_pct'] > 0 ? '−' . $l['discount_pct'] . ' %' : '—' }}</td>
                        <td style="padding: 8px 10px; text-align: right; font-weight: 600; white-space: nowrap;">{{ $eur($l['total']) }}</td>
                    </tr>
                @endforeach
                @if ($q['other_count'])
                    <tr style="border-top: 1px solid #f3f4f6; color: #6b7280;">
                        <td colspan="4" style="padding: 8px 10px;">{{ trans_choice('{1}Other item (other brands or services)|[2,*]Other items (other brands or services): :count', $q['other_count'], ['count' => $q['other_count']]) }}</td>
                        <td style="padding: 8px 10px; text-align: right; white-space: nowrap;">{{ $eur($q['other_total']) }}</td>
                    </tr>
                @endif
            </table>

            <table style="width: 100%; border-collapse: collapse; margin: 0 0 22px; font-size: 14px;" cellpadding="0" cellspacing="0">
                <tr><td style="padding: 4px 10px; text-align: right; color: #6b7280;">{{ __('Your products HT') }}</td><td style="padding: 4px 10px; text-align: right; width: 130px; font-weight: 700;">{{ $eur($q['value_ht']) }}</td></tr>
                @if ($q['discount_pct'] > 0)
                    <tr><td style="padding: 4px 10px; text-align: right; color: #6b7280;">{{ __('Discounts on your products') }}</td><td style="padding: 4px 10px; text-align: right; color: #dc2626;">−{{ $q['discount_pct'] }} %</td></tr>
                @endif
                @if ($q['global_discount_pct'] > 0)
                    <tr><td style="padding: 4px 10px; text-align: right; color: #6b7280;">{{ __('Quote-wide discount') }}</td><td style="padding: 4px 10px; text-align: right; color: #dc2626;">−{{ round($q['global_discount_pct'], 1) }} %</td></tr>
                @endif
                <tr><td style="padding: 4px 10px; text-align: right; color: #6b7280;">{{ __('Quote total HT') }}</td><td style="padding: 4px 10px; text-align: right;">{{ $eur($q['total_ht']) }}</td></tr>
            </table>

            <p style="margin: 0 0 20px; text-align: center;">
                <a href="{{ $url }}" style="display: inline-block; padding: 12px 28px; background: #0e4f79; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">{{ __('Open in your brand portal') }}</a>
            </p>
            <p style="margin: 0; font-size: 12px; color: #9ca3af;">{{ __('Client personal details, purchase prices and margins are never shared with brands.') }}</p>
        </div>
    </div>
</body>
</html>
