<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quote->order_confirmation_number }}</title>
    @include('pdf._styles')
</head>
<body>

{{-- §13 Header --}}
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
                <h2 style="font-size: 16pt;">ORDER CONFIRMATION</h2>
                <div class="meta">BON DE COMMANDE</div>
                <div class="meta">
                    <strong>{{ $quote->order_confirmation_number }}</strong><br>
                    Linked quote: {{ $quote->number }}<br>
                    Date: {{ $quote->order_confirmation_at?->format('F j, Y') }}
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- §13 Client details --}}
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

{{-- §13 Boat with full configuration summary --}}
<div class="section">
    <h3>Boat</h3>
    <strong>{{ $quote->model_snapshot['name'] ?? '' }}</strong> — {{ $quote->variant_snapshot['name'] ?? '' }}<br>
    <span class="meta">{{ $quote->model_snapshot['brand'] ?? '' }} · {{ $quote->model_snapshot['code'] ?? '' }}</span>
</div>

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

@if (! empty($quote->options))
    <div class="section">
        <h3>Selected options — final agreed prices</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="c" style="width:8%">Qty</th>
                    <th class="r" style="width:20%">Line HT</th>
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
                        <td class="r">€{{ number_format($opt['line_after_cat'] ?? 0, 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if (! empty($quote->custom_items))
    <div class="section">
        <h3>Additional services</h3>
        <table class="data">
            <tbody>
                @foreach ($quote->custom_items as $ci)
                    <tr>
                        <td>{{ $ci['label'] ?? '' }}</td>
                        <td class="r" style="width:20%">€{{ number_format($ci['line_after_cat'] ?? $ci['amount'] ?? 0, 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@php $t = $quote->totals ?? []; @endphp

<div class="clearfix">
    <table class="totals">
        <tr><td>Total HT</td><td class="r">€{{ number_format($t['total_ht'] ?? 0, 2, ',', ' ') }}</td></tr>
        <tr><td>VAT ({{ $t['vat_rate'] ?? 20 }}%)</td><td class="r">€{{ number_format($t['vat_amount'] ?? 0, 2, ',', ' ') }}</td></tr>
        <tr class="sub"><td>Total TTC</td><td class="r">€{{ number_format($t['total_ttc'] ?? 0, 2, ',', ' ') }}</td></tr>
        @if (($t['trade_in_deduction'] ?? 0) > 0)
            <tr><td>Trade-in deduction</td><td class="r">−€{{ number_format($t['trade_in_deduction'], 2, ',', ' ') }}</td></tr>
        @endif
        <tr class="grand"><td>Net payable</td><td class="r">€{{ number_format($t['net_payable'] ?? 0, 2, ',', ' ') }}</td></tr>
    </table>
</div>

{{-- §13 Signature block --}}
<div class="sig-block clearfix">
    <table>
        <tr>
            <td>
                <strong>Client</strong><br>
                <span class="muted">Signature + date</span>
                <div class="sig-box"></div>
            </td>
            <td>
                <strong>Salesperson ({{ $company->salesperson_name }})</strong><br>
                <span class="muted">Signature + date</span>
                <div class="sig-box"></div>
            </td>
        </tr>
    </table>
</div>

{{-- §13 Legal mentions --}}
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
