<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    @include('pdf._styles')
</head>
<body>

@php $t = $quote->totals ?? []; @endphp

{{-- ════════ Header: Company info  /  QUOTATION + reference grid ════════ --}}
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
            <div class="doc-title">QUOTATION</div>
            <table class="ref">
                <tr>
                    <th style="width:50%;">Quote #</th>
                    <th style="width:50%;">Date</th>
                </tr>
                <tr>
                    <td>{{ $quote->number }}</td>
                    <td>{{ $quote->created_at?->format('j M Y') }}</td>
                </tr>
                <tr>
                    <th>Customer ID</th>
                    <th>Valid until</th>
                </tr>
                <tr>
                    <td>{{ strtoupper(substr((string) ($quote->client_id ?? ''), -6)) ?: '—' }}</td>
                    <td>{{ $quote->expires_at?->format('j M Y') ?? '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ════════ Customer info  /  Prepared by ════════ --}}
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
                <em>Prepared by:</em><br>
                <strong>{{ $company->salesperson_name ?? '' }}</strong>
            </td>
        </tr>
    </table>
</div>

{{-- ════════ Description of work ════════ --}}
<div class="section-label">Description of work</div>
<div class="section-body">
    <p>
        Sale of <strong>{{ $quote->model_snapshot['name'] ?? '' }}</strong>
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
            <th class="r" style="width:12%;">Disc.</th>
            <th class="r" style="width:18%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        {{-- Boat hull row --}}
        <tr>
            <td>
                <strong>{{ $quote->model_snapshot['name'] ?? '' }} — {{ $quote->variant_snapshot['name'] ?? '' }}</strong><br>
                <span class="cat-tag">Hull · base price</span>
            </td>
            <td class="c">1</td>
            <td class="r">€{{ number_format($t['base_price_gross'] ?? 0, 2, ',', ' ') }}</td>
            <td class="r">
                @if (($t['boat_discount_pct'] ?? 0) > 0)
                    {{ number_format($t['boat_discount_pct'], 1) }}%
                @else — @endif
            </td>
            <td class="r">€{{ number_format($t['base_ht'] ?? 0, 2, ',', ' ') }}</td>
        </tr>

        {{-- Options --}}
        @foreach ($quote->options ?? [] as $opt)
            <tr>
                <td>
                    {{ $opt['label'] ?? '' }}<br>
                    <span class="cat-tag">{{ $opt['category'] ?? '' }}</span>
                </td>
                <td class="c">{{ $opt['quantity'] ?? 1 }}</td>
                <td class="r">€{{ number_format($opt['unit_price'] ?? 0, 2, ',', ' ') }}</td>
                <td class="r">
                    @php $d = ($opt['item_discount_pct'] ?? 0) + ($opt['cat_discount_pct'] ?? 0); @endphp
                    @if ($d > 0) {{ number_format($d, 1) }}% @else — @endif
                </td>
                <td class="r">€{{ number_format($opt['line_after_cat'] ?? 0, 2, ',', ' ') }}</td>
            </tr>
        @endforeach

        {{-- Custom items --}}
        @foreach ($quote->custom_items ?? [] as $ci)
            <tr>
                <td>
                    {{ $ci['label'] ?? '' }}<br>
                    <span class="cat-tag">Service</span>
                </td>
                <td class="c">1</td>
                <td class="r">€{{ number_format($ci['amount'] ?? 0, 2, ',', ' ') }}</td>
                <td class="r">
                    @if (($ci['item_discount_pct'] ?? 0) > 0) {{ number_format($ci['item_discount_pct'], 1) }}% @else — @endif
                </td>
                <td class="r">€{{ number_format($ci['line_after_cat'] ?? $ci['amount'] ?? 0, 2, ',', ' ') }}</td>
            </tr>
        @endforeach

        {{-- Trade-in row (if applicable) — shown as a credit line --}}
        @if (! empty($quote->trade_in) && (($quote->trade_in['value'] ?? 0) > 0))
            <tr>
                <td>
                    Trade-in credit<br>
                    <span class="cat-tag">Deducted from total</span>
                </td>
                <td class="c">1</td>
                <td class="r">−€{{ number_format($quote->trade_in['value'], 2, ',', ' ') }}</td>
                <td class="r">—</td>
                <td class="r">−€{{ number_format($quote->trade_in['value'], 2, ',', ' ') }}</td>
            </tr>
        @endif
    </tbody>
</table>

{{-- ════════ Totals strip ════════ --}}
<table class="totals">
    <tr>
        <td class="filler" style="width:60%;"></td>
        <td class="label" style="width:20%;">Subtotal HT</td>
        <td class="val">€{{ number_format($t['subtotal_ht'] ?? 0, 2, ',', ' ') }}</td>
    </tr>
    @if (($t['global_discount_amount'] ?? 0) > 0)
        <tr>
            <td class="filler"></td>
            <td class="label">Global discount ({{ $t['global_discount_pct'] }}%)</td>
            <td class="val">−€{{ number_format($t['global_discount_amount'], 2, ',', ' ') }}</td>
        </tr>
    @endif
    <tr>
        <td class="filler"></td>
        <td class="label">Total HT</td>
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

{{-- ════════ Footnote ════════ --}}
<div class="footnote">
    <p style="margin:0 0 1.5mm 0;">
        This quotation is not a contract or a bill. It is our best guess at the total price for the goods
        and services described above. The customer will be billed after indicating acceptance of this quote.
        @if ($quote->expires_at)
            This offer is valid until <strong>{{ $quote->expires_at->format('j M Y') }}</strong>.
        @endif
        Payment will be due prior to the delivery of goods. Please return the signed quote to the address
        listed above.
    </p>
</div>

{{-- ════════ Customer acceptance ════════ --}}
<div class="acceptance">
    <div class="heading">Customer acceptance</div>
    <table>
        <tr>
            <td style="width:50%;"><div class="sig-line"></div><div class="sig-cap">Signature</div></td>
            <td style="width:30%;"><div class="sig-line"></div><div class="sig-cap">Printed name</div></td>
            <td style="width:20%;"><div class="sig-line"></div><div class="sig-cap">Date</div></td>
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
