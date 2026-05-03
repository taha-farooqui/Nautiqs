<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quote->order_confirmation_number }}</title>
    @include('pdf._styles')
</head>
<body>

@php $t = $quote->totals ?? []; @endphp

{{-- ════════ Header ════════ --}}
<table class="doc-header">
    <tr>
        <td class="company-block" style="width:55%;">
            <div class="company-name">{{ $company->name ?? '[Company Name]' }}</div>
            <div class="meta">
                {{ $company->address ?? '' }}<br>
                @if ($company->salesperson_phone) Phone: {{ $company->salesperson_phone }}<br> @endif
                @if ($company->salesperson_email) Email: {{ $company->salesperson_email }} @endif
            </div>
        </td>
        <td style="width:45%;">
            <div class="doc-title">ORDER CONFIRMATION</div>
            <table class="ref">
                <tr>
                    <th style="width:50%;">Order #</th>
                    <th style="width:50%;">Date</th>
                </tr>
                <tr>
                    <td>{{ $quote->order_confirmation_number }}</td>
                    <td>{{ ($quote->order_confirmation_at ?? $quote->won_at)?->format('j M Y') }}</td>
                </tr>
                <tr>
                    <th>Linked quote</th>
                    <th>Customer ID</th>
                </tr>
                <tr>
                    <td>{{ $quote->number }}</td>
                    <td>{{ strtoupper(substr((string) ($quote->client_id ?? ''), -6)) ?: '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ════════ Customer info ════════ --}}
<div class="section-label">Customer info</div>
<div class="section-body">
    <table class="two-col-block">
        <tr>
            <td class="col-left">
                <strong>{{ trim(($quote->client_snapshot['first_name'] ?? '') . ' ' . ($quote->client_snapshot['last_name'] ?? '')) }}</strong><br>
                @if (! empty($quote->client_snapshot['company_name']))
                    {{ $quote->client_snapshot['company_name'] }}<br>
                @endif
                {{ $quote->client_snapshot['address_line'] ?? '' }}<br>
                {{ trim(($quote->client_snapshot['postal_code'] ?? '') . ' ' . ($quote->client_snapshot['city'] ?? '')) }}
                @if (! empty($quote->client_snapshot['country']))
                    , {{ $quote->client_snapshot['country'] }}
                @endif
                <br>
                @if (! empty($quote->client_snapshot['phone'])) {{ $quote->client_snapshot['phone'] }} · @endif
                {{ $quote->client_snapshot['email'] ?? '' }}
            </td>
            <td class="col-right">
                <em>Confirmed by:</em><br>
                <strong>{{ $company->salesperson_name ?? '' }}</strong>
            </td>
        </tr>
    </table>
</div>

{{-- ════════ Order summary ════════ --}}
<div class="section-label">Order summary</div>
<div class="section-body">
    <p>
        Confirmed sale of <strong>{{ $quote->model_snapshot['name'] ?? '' }}</strong>
        — {{ $quote->variant_snapshot['name'] ?? '' }}
        @if (! empty($quote->model_snapshot['brand']))
            ({{ $quote->model_snapshot['brand'] }})
        @endif.
    </p>
    @if (! empty($quote->included_equipment))
        <p class="small-meta" style="margin-top:2mm;">
            <strong>Standard equipment included:</strong>
            @foreach ($quote->included_equipment as $i => $eq)
                {{ $eq['label'] ?? '' }}{{ $i < count($quote->included_equipment) - 1 ? ', ' : '.' }}
            @endforeach
        </p>
    @endif
</div>

{{-- ════════ Itemized costs ════════ --}}
<div class="section-label" style="margin-top:6mm;">Itemized costs</div>
<table class="items">
    <thead>
        <tr>
            <th>Item</th>
            <th class="c" style="width:8%;">Qty</th>
            <th class="r" style="width:18%;">Unit price</th>
            <th class="r" style="width:18%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <strong>{{ $quote->model_snapshot['name'] ?? '' }} — {{ $quote->variant_snapshot['name'] ?? '' }}</strong><br>
                <span class="cat-tag">Hull · base price</span>
            </td>
            <td class="c">1</td>
            <td class="r">€{{ number_format($t['base_price_gross'] ?? 0, 2, ',', ' ') }}</td>
            <td class="r">€{{ number_format($t['base_ht'] ?? 0, 2, ',', ' ') }}</td>
        </tr>

        @foreach ($quote->options ?? [] as $opt)
            <tr>
                <td>
                    {{ $opt['label'] ?? '' }}<br>
                    <span class="cat-tag">{{ $opt['category'] ?? '' }}</span>
                </td>
                <td class="c">{{ $opt['quantity'] ?? 1 }}</td>
                <td class="r">€{{ number_format($opt['unit_price'] ?? 0, 2, ',', ' ') }}</td>
                <td class="r">€{{ number_format($opt['line_after_cat'] ?? 0, 2, ',', ' ') }}</td>
            </tr>
        @endforeach

        @foreach ($quote->custom_items ?? [] as $ci)
            <tr>
                <td>
                    {{ $ci['label'] ?? '' }}<br>
                    <span class="cat-tag">Service</span>
                </td>
                <td class="c">1</td>
                <td class="r">€{{ number_format($ci['amount'] ?? 0, 2, ',', ' ') }}</td>
                <td class="r">€{{ number_format($ci['line_after_cat'] ?? $ci['amount'] ?? 0, 2, ',', ' ') }}</td>
            </tr>
        @endforeach

        @if (! empty($quote->trade_in) && (($quote->trade_in['value'] ?? 0) > 0))
            <tr>
                <td>
                    Trade-in credit<br>
                    <span class="cat-tag">Deducted from total</span>
                </td>
                <td class="c">1</td>
                <td class="r">−€{{ number_format($quote->trade_in['value'], 2, ',', ' ') }}</td>
                <td class="r">−€{{ number_format($quote->trade_in['value'], 2, ',', ' ') }}</td>
            </tr>
        @endif
    </tbody>
</table>

{{-- ════════ Totals ════════ --}}
<table class="totals">
    <tr>
        <td class="filler" style="width:60%;"></td>
        <td class="label" style="width:20%;">Total HT</td>
        <td class="val">€{{ number_format($t['total_ht'] ?? 0, 2, ',', ' ') }}</td>
    </tr>
    <tr>
        <td class="filler"></td>
        <td class="label">VAT ({{ $t['vat_rate'] ?? 20 }}%)</td>
        <td class="val">€{{ number_format($t['vat_amount'] ?? 0, 2, ',', ' ') }}</td>
    </tr>
    <tr>
        <td class="filler"></td>
        <td class="label">Total TTC</td>
        <td class="val">€{{ number_format($t['total_ttc'] ?? 0, 2, ',', ' ') }}</td>
    </tr>
    @if (($t['trade_in_deduction'] ?? 0) > 0)
        <tr>
            <td class="filler"></td>
            <td class="label">Trade-in deduction</td>
            <td class="val">−€{{ number_format($t['trade_in_deduction'], 2, ',', ' ') }}</td>
        </tr>
    @endif
    <tr class="grand">
        <td class="filler"></td>
        <td class="label">Net payable</td>
        <td class="val">€{{ number_format($t['net_payable'] ?? 0, 2, ',', ' ') }}</td>
    </tr>
</table>

{{-- ════════ Payment terms ════════ --}}
<div class="footnote">
    <strong>Payment terms.</strong>
    A 30% deposit is required upon signature of this order confirmation, with the balance due before
    delivery. Delivery date will be confirmed in writing once the deposit is received. Specifications
    and equipment listed above are firm and binding for both parties.
</div>

{{-- ════════ Customer acceptance ════════ --}}
<div class="acceptance">
    <div class="heading">Signatures</div>
    <table>
        <tr>
            <td style="width:50%;">
                <div class="sig-line"></div>
                <div class="sig-cap">Customer signature &amp; date</div>
            </td>
            <td style="width:50%; padding-left:6mm;">
                <div class="sig-line"></div>
                <div class="sig-cap">{{ $company->salesperson_name ?? $company->name }} — signature &amp; date</div>
            </td>
        </tr>
    </table>
</div>

{{-- ════════ Bottom contact ════════ --}}
<div class="bottom-contact">
    If you have any questions, please contact
    {{ $company->salesperson_name }}{{ $company->salesperson_phone ? ', ' . $company->salesperson_phone : '' }}{{ $company->salesperson_email ? ', ' . $company->salesperson_email : '' }}.
</div>

{{-- ════════ Footer ════════ --}}
<div class="footer">
    <strong>{{ $company->name }}</strong>
    @if ($company->legal_form) · {{ $company->legal_form }} @endif
    @if ($company->siren) · SIREN {{ $company->siren }} @endif
    @if ($company->vat_number) · VAT {{ $company->vat_number }} @endif
    · {{ str_replace("\n", ', ', $company->address ?? '') }}
</div>

</body>
</html>
