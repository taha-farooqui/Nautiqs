<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    @include('pdf._styles')
</head>
<body>

@php
    $t        = $quote->totals ?? [];
    $clientFn = trim(($quote->client_snapshot['first_name'] ?? '') . ' ' . ($quote->client_snapshot['last_name'] ?? ''));
    $clientCo = $quote->client_snapshot['company_name'] ?? '';
    $clientAddrLine = $quote->client_snapshot['address_line'] ?? '';
    $clientCityLine = trim(($quote->client_snapshot['postal_code'] ?? '') . ' ' . ($quote->client_snapshot['city'] ?? ''));
    if (! empty($quote->client_snapshot['country'])) {
        $clientCityLine = trim($clientCityLine . ', ' . $quote->client_snapshot['country']);
    }

    // Group options by category for the table
    $optionRows = collect($quote->options ?? [])->groupBy(fn ($o) => $o['category'] ?? 'Options');

    // Salesperson initials for the avatar tile (text-only fallback for the
    // template's coloured circle)
    $spName = $company->salesperson_name ?? '';
    $spInitials = strtoupper(collect(preg_split('/\s+/', $spName))
        ->filter()
        ->map(fn ($p) => mb_substr($p, 0, 1))
        ->take(2)
        ->join(''));
@endphp

{{-- ════════════════════════════ HEADER ════════════════════════════ --}}
<table class="qhead">
    <tr>
        <td style="width:55%;">
            <div class="qhead-name">{{ $company->name ?? 'Nautiqs' }}</div>
            <div class="qhead-sub">
                @if ($company->address) {{ $company->address }}<br> @endif
                @if ($company->salesperson_phone) {{ $company->salesperson_phone }} @endif
                @if ($company->salesperson_phone && $company->salesperson_email) · @endif
                @if ($company->salesperson_email) {{ $company->salesperson_email }} @endif
            </div>
        </td>
        <td style="width:45%; text-align:right;">
            <div class="qhead-doctype">Quotation</div>
            <div class="qhead-ref">{{ $quote->number }}</div>
            <div class="qhead-date">{{ $quote->created_at?->format('F j, Y') }}</div>
            @if ($quote->expires_at)
                <div class="qhead-validity">Valid until {{ $quote->expires_at->format('F j, Y') }}</div>
            @endif
        </td>
    </tr>
</table>
<div class="qhead-strip"></div>

{{-- ════════════════════════════ META ROW ═════════════════════════ --}}
<table class="qmeta">
    <tr>
        <td>
            <div class="qmeta-label">Client</div>
            <div class="qmeta-name">{{ $clientFn ?: 'Guest' }}</div>
            <div class="qmeta-detail">
                @if ($clientCo) {{ $clientCo }}<br> @endif
                @if (! empty($quote->client_snapshot['email'])){{ $quote->client_snapshot['email'] }}<br>@endif
                @if (! empty($quote->client_snapshot['phone'])){{ $quote->client_snapshot['phone'] }}<br>@endif
                @if ($clientAddrLine) {{ $clientAddrLine }}<br> @endif
                @if ($clientCityLine) {{ $clientCityLine }} @endif
            </div>
        </td>
        <td>
            <div class="qmeta-label">Your contact</div>
            <div class="qmeta-name">{{ $spName ?: $company->name }}</div>
            <div class="qmeta-detail">
                @if ($company->salesperson_phone) {{ $company->salesperson_phone }}<br>@endif
                @if ($company->salesperson_email) {{ $company->salesperson_email }} @endif
            </div>
        </td>
    </tr>
</table>

{{-- ════════════════════════════ BOAT BAND ═══════════════════════ --}}
<table class="qboat">
    <tr>
        <td style="width:60%;">
            <div class="qboat-name">
                @if (! empty($quote->model_snapshot['brand'])) {{ $quote->model_snapshot['brand'] }} @endif
                {{ $quote->model_snapshot['name'] ?? '' }}
            </div>
            <div class="qboat-variant">
                {{ $quote->variant_snapshot['name'] ?? '' }}
                @if (! empty($quote->model_snapshot['code']))
                    · {{ $quote->model_snapshot['code'] }}
                @endif
            </div>
        </td>
        <td style="width:40%; text-align:right;">
            @if (! empty($quote->variant_snapshot['currency']))
                <div class="qboat-spec">Currency</div>
                <div class="qboat-spec-value">{{ $quote->variant_snapshot['currency'] }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- ════════════ INCLUDED EQUIPMENT ════════════ --}}
@if (! empty($quote->included_equipment))
    <div class="qsection">
        <span class="qsection-title">Standard included equipment</span>
        <span class="qsection-badge">Included in base price</span>
    </div>
    <table class="qincluded">
        @php
            $equip = collect($quote->included_equipment)->values();
            $rows = $equip->chunk(2);
        @endphp
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $eq)
                    <td><span class="qcheck">v</span>{{ $eq['label'] ?? '' }}</td>
                @endforeach
                @if ($row->count() < 2) <td></td> @endif
            </tr>
        @endforeach
    </table>
@endif

{{-- ════════════ OPTIONS TABLE ════════════ --}}
<div class="qsection">
    <span class="qsection-title">Selected options &amp; services</span>
</div>

<table class="qoptions">
    {{-- Base boat row --}}
    <tr class="cat-row"><td colspan="4">Base boat</td></tr>
    <tr class="item-row">
        <td><span class="qopt-name">{{ $quote->model_snapshot['name'] ?? '' }} — {{ $quote->variant_snapshot['name'] ?? '' }}</span></td>
        <td class="qopt-qty" style="width:12mm;">1</td>
        <td class="qopt-unit" style="width:30mm;">€{{ number_format($t['base_price_gross'] ?? 0, 2, ',', ' ') }}</td>
        <td class="qopt-total" style="width:32mm;">€{{ number_format($t['base_ht'] ?? 0, 2, ',', ' ') }}</td>
    </tr>
    @if (($t['boat_discount_pct'] ?? 0) > 0)
        <tr class="item-row">
            <td><span class="qopt-name" style="color:#9ca3af;">Boat discount ({{ number_format($t['boat_discount_pct'], 1) }}%)</span></td>
            <td class="qopt-qty"></td>
            <td class="qopt-unit"></td>
            <td class="qopt-total discount-applied">−€{{ number_format($t['boat_discount_amount'] ?? 0, 2, ',', ' ') }}</td>
        </tr>
    @endif

    {{-- Options grouped by category --}}
    @foreach ($optionRows as $category => $items)
        <tr class="cat-row"><td colspan="4">{{ $category }}</td></tr>
        @foreach ($items as $opt)
            @php
                $itemDisc = (float) ($opt['item_discount_pct'] ?? 0);
                $catDisc  = (float) ($opt['cat_discount_pct'] ?? 0);
                $totalDisc = $itemDisc + $catDisc;
                $unit = (float) ($opt['unit_price'] ?? 0);
                $line = (float) ($opt['line_after_cat'] ?? 0);
            @endphp
            <tr class="item-row">
                <td>
                    <span class="qopt-name">{{ $opt['label'] ?? '' }}</span>
                    @if ($itemDisc > 0)
                        <span class="qopt-disc-badge">−{{ number_format($itemDisc, 0) }}%</span>
                    @endif
                </td>
                <td class="qopt-qty">{{ $opt['quantity'] ?? 1 }}</td>
                <td class="qopt-unit">
                    @if ($totalDisc > 0)
                        <span class="qopt-strike">€{{ number_format($unit, 2, ',', ' ') }}</span>
                    @else
                        €{{ number_format($unit, 2, ',', ' ') }}
                    @endif
                </td>
                <td class="qopt-total {{ $totalDisc > 0 ? 'discount-applied' : '' }}">
                    €{{ number_format($line, 2, ',', ' ') }}
                </td>
            </tr>
        @endforeach
    @endforeach

    {{-- Custom items --}}
    @if (! empty($quote->custom_items))
        <tr class="cat-row"><td colspan="4">Services</td></tr>
        @foreach ($quote->custom_items as $ci)
            <tr class="item-row">
                <td><span class="qopt-name">{{ $ci['label'] ?? '' }}</span></td>
                <td class="qopt-qty">1</td>
                <td class="qopt-unit">€{{ number_format($ci['amount'] ?? 0, 2, ',', ' ') }}</td>
                <td class="qopt-total">€{{ number_format($ci['line_after_cat'] ?? $ci['amount'] ?? 0, 2, ',', ' ') }}</td>
            </tr>
        @endforeach
    @endif
</table>

{{-- ════════════ CONDITIONS + TOTALS ════════════ --}}
<table class="qbottom">
    <tr>
        {{-- Left: terms & conditions --}}
        <td class="left">
            <div class="qcond-title">Terms &amp; conditions</div>
            <table class="qcond">
                <tr><td class="label">Payment</td><td class="val">30% on order · 70% on delivery</td></tr>
                <tr><td class="label">Delivery</td><td class="val">8 to 12 weeks depending on availability</td></tr>
                <tr><td class="label">Quote valid</td>
                    <td class="val">
                        @if ($quote->expires_at)
                            until {{ $quote->expires_at->format('F j, Y') }}
                        @else
                            30 days from issue
                        @endif
                    </td></tr>
                <tr><td class="label">Warranty</td><td class="val">Manufacturer warranty + dealer prep</td></tr>
            </table>

            @if (! empty($quote->trade_in) && (($quote->trade_in['value'] ?? 0) > 0))
                <div class="qtradein">
                    <div class="qtradein-title">Trade-in included</div>
                    <div class="qtradein-detail">
                        @if (! empty($quote->trade_in['description']))
                            {{ $quote->trade_in['description'] }}<br>
                        @endif
                        Trade-in value: <strong>€{{ number_format($quote->trade_in['value'], 2, ',', ' ') }}</strong>
                    </div>
                </div>
            @endif
        </td>

        {{-- Right: totals box --}}
        <td class="right">
            <table class="qtotals">
                <tr class="row-white">
                    <td class="label">Subtotal excl. VAT</td>
                    <td class="val">€{{ number_format($t['subtotal_ht'] ?? 0, 2, ',', ' ') }}</td>
                </tr>
                @if (($t['global_discount_amount'] ?? 0) > 0)
                    <tr class="row-discount row-white">
                        <td class="label">Global discount ({{ number_format($t['global_discount_pct'] ?? 0, 1) }}%)</td>
                        <td class="val">−€{{ number_format($t['global_discount_amount'], 2, ',', ' ') }}</td>
                    </tr>
                @endif
                <tr class="row-white">
                    <td class="label">Total excl. VAT</td>
                    <td class="val">€{{ number_format($t['total_ht'] ?? 0, 2, ',', ' ') }}</td>
                </tr>
                <tr class="row-white">
                    <td class="label">VAT ({{ number_format($t['vat_rate'] ?? 20, 0) }}%)</td>
                    <td class="val">+€{{ number_format($t['vat_amount'] ?? 0, 2, ',', ' ') }}</td>
                </tr>
                <tr class="row-ttc">
                    <td class="label">Total incl. VAT</td>
                    <td class="val">€{{ number_format($t['total_ttc'] ?? 0, 2, ',', ' ') }}</td>
                </tr>
                @if (($t['trade_in_deduction'] ?? 0) > 0)
                    <tr class="row-tradein">
                        <td class="label">Trade-in deduction</td>
                        <td class="val">−€{{ number_format($t['trade_in_deduction'], 2, ',', ' ') }}</td>
                    </tr>
                @endif
                <tr class="row-net">
                    <td class="label">Net payable</td>
                    <td class="val">€{{ number_format($t['net_payable'] ?? $t['total_ttc'] ?? 0, 2, ',', ' ') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ════════════ SIGNATURES ════════════ --}}
<table class="qsign">
    <tr>
        <td>
            <div class="qsign-title">Client acceptance — signature</div>
            <div class="qsign-area">
                <div class="qsign-line">{{ $clientFn ?: 'Client name' }} &nbsp; · &nbsp; Date: __ / __ / ____</div>
            </div>
            <div class="qsign-meta">By signing, the client accepts all terms and conditions of this quotation.</div>
        </td>
        <td>
            <div class="qsign-title">Salesperson signature</div>
            <div class="qsign-area">
                <div class="qsign-line">{{ $spName ?: $company->name }} &nbsp; · &nbsp; Date: __ / __ / ____</div>
            </div>
            <div class="qsign-meta">{{ $company->name }}</div>
        </td>
    </tr>
</table>

{{-- ════════════ FOOTER (every page) ════════════ --}}
<div class="qfooter">
    <div class="legal">
        <strong>{{ $company->name }}</strong>
        @if ($company->legal_form) · {{ $company->legal_form }} @endif
        @if ($company->siren) · SIREN {{ $company->siren }} @endif
        @if ($company->vat_number) · VAT {{ $company->vat_number }} @endif
        <br>
        @if ($company->address) {{ str_replace("\n", ' · ', $company->address) }} @endif
    </div>
    <div class="page">Page <span class="pagenum"></span></div>
</div>

</body>
</html>
