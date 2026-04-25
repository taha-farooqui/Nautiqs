<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    @include('pdf._styles')
</head>
<body>

{{-- §12 Header: company logo, salesperson name / phone / email --}}
<div class="brand-bar">
    <table class="header-grid">
        <tr>
            <td style="width: 60%;">
                <h1 style="font-size: 20pt; margin-bottom: 2px;">{{ $company->name }}</h1>
                <div class="meta">
                    {{ $company->salesperson_name }}<br>
                    {{ $company->salesperson_phone }} · {{ $company->salesperson_email }}
                </div>
            </td>
            <td style="width: 40%; text-align: right;">
                <h2 style="font-size: 16pt;">QUOTATION</h2>
                <div class="meta">
                    <strong>{{ $quote->number }}</strong><br>
                    Date: {{ $quote->created_at?->format('F j, Y') }}
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- §12 Client details --}}
<div class="section">
    <h3>Client</h3>
    <div>
        <strong>{{ trim(($quote->client_snapshot['first_name'] ?? '') . ' ' . ($quote->client_snapshot['last_name'] ?? '')) }}</strong>
        @if (! empty($quote->client_snapshot['company_name']))
            <br>{{ $quote->client_snapshot['company_name'] }}
        @endif
        <br>{{ $quote->client_snapshot['email'] ?? '' }}
        @if (! empty($quote->client_snapshot['phone']))
            · {{ $quote->client_snapshot['phone'] }}
        @endif
        <br>
        {{ $quote->client_snapshot['address_line'] ?? '' }}
        @if (! empty($quote->client_snapshot['postal_code']) || ! empty($quote->client_snapshot['city']))
            <br>{{ trim(($quote->client_snapshot['postal_code'] ?? '') . ' ' . ($quote->client_snapshot['city'] ?? '')) }}
        @endif
        @if (! empty($quote->client_snapshot['country']))
            <br>{{ $quote->client_snapshot['country'] }}
        @endif
    </div>
</div>

{{-- §12 Boat --}}
<div class="section">
    <h3>Boat</h3>
    <strong>{{ $quote->model_snapshot['name'] ?? '' }}</strong> — {{ $quote->variant_snapshot['name'] ?? '' }}<br>
    <span class="meta">{{ $quote->model_snapshot['brand'] ?? '' }} · {{ $quote->model_snapshot['code'] ?? '' }}</span>
</div>

{{-- §12 Included equipment — visually separated from options --}}
@if (! empty($quote->included_equipment))
    <div class="section">
        <h3>Included equipment</h3>
        <table class="data">
            <tbody>
                @foreach ($quote->included_equipment as $eq)
                    <tr><td>✓ {{ $eq['label'] ?? '' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- §12 Selected options with unit price and discounts --}}
@if (! empty($quote->options))
    <div class="section">
        <h3>Paid options</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="c" style="width:8%">Qty</th>
                    <th class="r" style="width:16%">Unit HT</th>
                    <th class="r" style="width:10%">Disc.</th>
                    <th class="r" style="width:18%">Line HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quote->options as $opt)
                    <tr>
                        <td>
                            <strong>{{ $opt['label'] ?? '' }}</strong>
                            <div class="muted">{{ $opt['category'] ?? '' }}</div>
                        </td>
                        <td class="c">{{ $opt['quantity'] ?? 1 }}</td>
                        <td class="r">€{{ number_format($opt['unit_price'] ?? 0, 2, ',', ' ') }}</td>
                        <td class="r">
                            @php $d = ($opt['item_discount_pct'] ?? 0) + ($opt['cat_discount_pct'] ?? 0); @endphp
                            {{ $d > 0 ? $d . '%' : '—' }}
                        </td>
                        <td class="r">€{{ number_format($opt['line_after_cat'] ?? 0, 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- §12 Custom line items --}}
@if (! empty($quote->custom_items))
    <div class="section">
        <h3>Additional services</h3>
        <table class="data">
            <tbody>
                @foreach ($quote->custom_items as $ci)
                    <tr>
                        <td>{{ $ci['label'] ?? '' }}</td>
                        <td class="r" style="width:18%">€{{ number_format($ci['line_after_cat'] ?? $ci['amount'] ?? 0, 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- §12 Discounts (already factored above but we show the global line explicitly) --}}
@php $t = $quote->totals ?? []; @endphp

{{-- §12 Trade-in --}}
@if (! empty($quote->trade_in))
    <div class="section">
        <h3>Trade-in</h3>
        <div class="meta">
            {{ $quote->trade_in['brand'] ?? '' }} {{ $quote->trade_in['model'] ?? '' }}
            @if (! empty($quote->trade_in['year'])) ({{ $quote->trade_in['year'] }}) @endif
            @if (! empty($quote->trade_in['engine'])) · {{ $quote->trade_in['engine'] }} @endif
            @if (! empty($quote->trade_in['engine_hours'])) · {{ $quote->trade_in['engine_hours'] }}h @endif
        </div>
        @if (! empty($quote->trade_in['description']))
            <div class="meta">{{ $quote->trade_in['description'] }}</div>
        @endif
    </div>
@endif

{{-- §12 Totals — NO margin, NO cost, NO internal notes --}}
<div class="clearfix">
    <table class="totals">
        <tr><td>Subtotal HT</td><td class="r">€{{ number_format($t['subtotal_ht'] ?? 0, 2, ',', ' ') }}</td></tr>
        @if (($t['global_discount_amount'] ?? 0) > 0)
            <tr><td>Global discount ({{ $t['global_discount_pct'] ?? 0 }}%)</td><td class="r">−€{{ number_format($t['global_discount_amount'], 2, ',', ' ') }}</td></tr>
        @endif
        <tr class="sub"><td>Total HT</td><td class="r">€{{ number_format($t['total_ht'] ?? 0, 2, ',', ' ') }}</td></tr>
        <tr><td>VAT ({{ $t['vat_rate'] ?? 20 }}%)</td><td class="r">€{{ number_format($t['vat_amount'] ?? 0, 2, ',', ' ') }}</td></tr>
        <tr class="sub"><td>Total TTC</td><td class="r">€{{ number_format($t['total_ttc'] ?? 0, 2, ',', ' ') }}</td></tr>
        @if (($t['trade_in_deduction'] ?? 0) > 0)
            <tr><td>Trade-in deduction</td><td class="r">−€{{ number_format($t['trade_in_deduction'], 2, ',', ' ') }}</td></tr>
        @endif
        <tr class="grand"><td>Net payable</td><td class="r">€{{ number_format($t['net_payable'] ?? 0, 2, ',', ' ') }}</td></tr>
    </table>
</div>

{{-- §12 Legal footer --}}
<div class="footer">
    <strong>{{ $company->name }}</strong>
    @if ($company->legal_form) · {{ $company->legal_form }} @endif
    @if ($company->siren) · SIREN {{ $company->siren }} @endif
    @if ($company->vat_number) · VAT {{ $company->vat_number }} @endif
    <br>
    {{ str_replace("\n", ' · ', $company->address ?? '') }}
</div>

</body>
</html>
