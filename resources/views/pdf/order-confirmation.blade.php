<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quote->order_confirmation_number }}</title>
    @include('pdf._styles')
</head>
<body>

@php
    $t = $quote->totals ?? [];
    $clientFn = trim(($quote->client_snapshot['first_name'] ?? '') . ' ' . ($quote->client_snapshot['last_name'] ?? ''));
    $clientCo = $quote->client_snapshot['company_name'] ?? '';
    $clientAddrLine = $quote->client_snapshot['address_line'] ?? '';
    $clientCityLine = trim(($quote->client_snapshot['postal_code'] ?? '') . ' ' . ($quote->client_snapshot['city'] ?? ''));
    if (! empty($quote->client_snapshot['country'])) {
        $clientCityLine = trim($clientCityLine . ', ' . $quote->client_snapshot['country']);
    }
    $optionRows = collect($quote->options ?? [])->groupBy(fn ($o) => $o['category'] ?? __('Options'));
    $confirmDate = $quote->order_confirmation_at ?? $quote->won_at ?? $quote->created_at;
    // The person who actually made the quote signs it; company-level
    // salesperson is the fallback for legacy quotes without a creator.
    $spName = $quote->creatorName() ?: ($company->salesperson_name ?? '');
    $logoSrc = $company->logoDataUri();
@endphp

{{-- ════════════════════════════ HEADER ════════════════════════════ --}}
<table class="qhead">
    <tr>
        <td style="width:55%;">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" class="qhead-logo" alt="{{ $company->name }}" />
            @else
                <div class="qhead-name">{{ $company->name ?? 'Nautiqs' }}</div>
            @endif
            <div class="qhead-sub">
                @if ($logoSrc && $company->name) <strong>{{ $company->name }}</strong><br> @endif
                @if ($company->address) {{ $company->address }}<br> @endif
                @if ($company->salesperson_phone) {{ $company->salesperson_phone }} @endif
                @if ($company->salesperson_phone && $company->salesperson_email) · @endif
                @if ($company->salesperson_email) {{ $company->salesperson_email }} @endif
            </div>
        </td>
        <td style="width:45%; text-align:right;">
            <div class="qhead-doctype">{{ __('Order confirmation') }}</div>
            <div class="qhead-ref">{{ $quote->order_confirmation_number }}</div>
            <div class="qhead-date">{{ $confirmDate?->format('d/m/Y') }}</div>
            <div class="qhead-validity">{{ __('Linked quote') }} {{ $quote->number }}</div>
        </td>
    </tr>
</table>
<div class="qhead-strip"></div>

{{-- ════════════════════════════ META ROW ═════════════════════════ --}}
<table class="qmeta">
    <tr>
        <td>
            <div class="qmeta-label">{{ __('Bill to') }}</div>
            <div class="qmeta-name">{{ $clientFn ?: __('Guest') }}</div>
            <div class="qmeta-detail">
                @if ($clientCo) {{ $clientCo }}<br> @endif
                @if (! empty($quote->client_snapshot['email'])){{ $quote->client_snapshot['email'] }}<br>@endif
                @if (! empty($quote->client_snapshot['phone'])){{ $quote->client_snapshot['phone'] }}<br>@endif
                @if ($clientAddrLine) {{ $clientAddrLine }}<br> @endif
                @if ($clientCityLine) {{ $clientCityLine }} @endif
            </div>
        </td>
        <td class="spacer"></td>
        <td>
            <div class="qmeta-label">{{ __('Your contact') }}</div>
            <div class="qmeta-name">{{ $spName ?: $company->name }}</div>
            <div class="qmeta-detail">
                @if ($company->salesperson_phone) {{ $company->salesperson_phone }}<br>@endif
                @if ($company->salesperson_email) {{ $company->salesperson_email }} @endif
            </div>
        </td>
    </tr>
</table>

{{-- ════════════════════════════ BOAT HEADLINE ═══════════════════ --}}
<table class="qboat">
    <tr>
        <td style="width:62%;">
            <div class="qboat-name">
                @if (! empty($quote->model_snapshot['brand'])) {{ $quote->model_snapshot['brand'] }} @endif
                {{ $quote->model_snapshot['name'] ?? '' }}
            </div>
            <div class="qboat-variant">
                {{ $quote->variant_snapshot['name'] ?? '' }}
            </div>
        </td>
        <td class="right" style="width:38%;">
            <div class="qboat-spec">{{ __('Confirmation date') }}</div>
            <div class="qboat-spec-value">{{ $confirmDate?->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>

{{-- ════════════ ORDER SUMMARY (options table) ════════════ --}}
<div class="qsection">
    <span class="qsection-title">{{ __('Confirmed configuration') }}</span>
</div>

<table class="qoptions">
    {{-- Column captions --}}
    <tr class="head-row">
        <td>{{ __('Description') }}</td>
        <td style="width:12mm; text-align:center;">{{ __('Qty') }}</td>
        <td style="width:30mm; text-align:right;">{{ __('Unit price HT') }}</td>
        <td style="width:32mm; text-align:right;">{{ __('Total HT') }}</td>
    </tr>

    <tr class="cat-row"><td colspan="4">{{ __('Base boat') }}</td></tr>
    @php
        // Avoid "Sun Odyssey 410 — Sun Odyssey 410 — Standard": if the variant
        // name already contains the model name, show the variant alone.
        $bModel   = $quote->model_snapshot['name'] ?? '';
        $bVariant = $quote->variant_snapshot['name'] ?? '';
        if ($bVariant === '') {
            $baseLabel = $bModel;
        } elseif ($bModel !== '' && stripos($bVariant, $bModel) !== false) {
            $baseLabel = $bVariant;
        } else {
            $baseLabel = trim($bModel . ' — ' . $bVariant, ' —');
        }
    @endphp
    <tr class="item-row">
        <td><span class="qopt-name">{{ $baseLabel }}</span></td>
        <td class="qopt-qty" style="width:12mm;">1</td>
        <td class="qopt-unit" style="width:30mm;">{{ number_format($t['base_price_gross'] ?? 0, 2, ',', ' ') }} €</td>
        <td class="qopt-total" style="width:32mm;">{{ number_format($t['base_ht'] ?? 0, 2, ',', ' ') }} €</td>
    </tr>

    @foreach ($optionRows as $category => $items)
        <tr class="cat-row"><td colspan="4">{{ $category }}</td></tr>
        @foreach ($items as $opt)
            <tr class="item-row">
                <td><span class="qopt-name">{{ $opt['label'] ?? '' }}</span></td>
                <td class="qopt-qty">{{ $opt['quantity'] ?? 1 }}</td>
                <td class="qopt-unit">{{ number_format($opt['unit_price'] ?? 0, 2, ',', ' ') }} €</td>
                <td class="qopt-total">{{ number_format($opt['line_after_cat'] ?? 0, 2, ',', ' ') }} €</td>
            </tr>
        @endforeach
    @endforeach

    @if (! empty($quote->custom_items))
        <tr class="cat-row"><td colspan="4">{{ __('Services') }}</td></tr>
        @foreach ($quote->custom_items as $ci)
            <tr class="item-row">
                <td><span class="qopt-name">{{ $ci['label'] ?? '' }}</span></td>
                <td class="qopt-qty">1</td>
                <td class="qopt-unit">{{ number_format($ci['amount'] ?? 0, 2, ',', ' ') }} €</td>
                <td class="qopt-total">{{ number_format($ci['line_after_cat'] ?? $ci['amount'] ?? 0, 2, ',', ' ') }} €</td>
            </tr>
        @endforeach
    @endif
</table>

{{-- ════════════ TOTALS ════════════ --}}
<table class="qbottom">
    <tr>
        <td class="left">
            <div class="qcond-title">{{ __('Order details') }}</div>
            <table class="qcond">
                <tr><td class="label">{{ __('Confirmed on') }}</td><td class="val">{{ $confirmDate?->format('d/m/Y') }}</td></tr>
                <tr><td class="label">{{ __('Linked quote') }}</td><td class="val">{{ $quote->number }}</td></tr>
                <tr><td class="label">{{ __('Payment') }}</td><td class="val">{{ __('As agreed in the quote') }}</td></tr>
                <tr><td class="label">{{ __('Delivery') }}</td><td class="val">{{ __('8 to 12 weeks depending on availability') }}</td></tr>
            </table>

            @if (! empty($quote->trade_in) && (($quote->trade_in['value'] ?? 0) > 0))
                <div class="qtradein">
                    <div class="qtradein-title">{{ __('Trade-in included') }}</div>
                    <div class="qtradein-detail">
                        @if (! empty($quote->trade_in['description']))
                            {{ $quote->trade_in['description'] }}<br>
                        @endif
                        {{ __('Trade-in value') }}: <strong>{{ number_format($quote->trade_in['value'], 2, ',', ' ') }} €</strong>
                    </div>
                </div>
            @endif
        </td>

        <td class="right">
            <table class="qtotals">
                <tr class="row-white">
                    <td class="label">{{ __('Subtotal excl. VAT') }}</td>
                    <td class="val">{{ number_format($t['subtotal_ht'] ?? 0, 2, ',', ' ') }} €</td>
                </tr>
                <tr class="row-white">
                    <td class="label">{{ __('Total excl. VAT') }}</td>
                    <td class="val">{{ number_format($t['total_ht'] ?? 0, 2, ',', ' ') }} €</td>
                </tr>
                <tr class="row-white">
                    <td class="label">{{ __('VAT') }} ({{ number_format($t['vat_rate'] ?? 20, 0) }}%)</td>
                    <td class="val">+{{ number_format($t['vat_amount'] ?? 0, 2, ',', ' ') }} €</td>
                </tr>
                <tr class="row-ttc">
                    <td class="label">{{ __('Total incl. VAT') }}</td>
                    <td class="val">{{ number_format($t['total_ttc'] ?? 0, 2, ',', ' ') }} €</td>
                </tr>
                @if (($t['trade_in_deduction'] ?? 0) > 0)
                    <tr class="row-tradein">
                        <td class="label">{{ __('Trade-in deduction') }}</td>
                        <td class="val">-{{ number_format($t['trade_in_deduction'], 2, ',', ' ') }} €</td>
                    </tr>
                @endif
                <tr class="row-net">
                    <td class="label">{{ __('Net payable') }}</td>
                    <td class="val">{{ number_format($t['net_payable'] ?? $t['total_ttc'] ?? 0, 2, ',', ' ') }} €</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ════════════ SIGNATURES ════════════ --}}
<table class="qsign">
    <tr>
        <td>
            <div class="qsign-title">{{ __('Buyer — order acceptance') }}</div>
            <div class="qsign-area">
                <div class="qsign-line">{{ $clientFn ?: __('Client name') }} &nbsp; · &nbsp; {{ __('Date') }}: __ / __ / ____</div>
            </div>
            <div class="qsign-meta">{{ __('Signature constitutes acceptance of the order and binding agreement to pay.') }}</div>
        </td>
        <td>
            <div class="qsign-title">{{ __('Seller — confirmation') }}</div>
            <div class="qsign-area">
                <div class="qsign-line">{{ $spName ?: $company->name }} &nbsp; · &nbsp; {{ __('Date') }}: __ / __ / ____</div>
            </div>
            <div class="qsign-meta">{{ $company->name }}</div>
        </td>
    </tr>
</table>

{{-- ════════════ FOOTER ════════════ --}}
<div class="qfooter">
    <div class="legal">
        <strong>{{ $company->name }}</strong>
        @if ($company->legal_form) · {{ $company->legal_form }} @endif
        @if ($company->siren) · SIREN {{ $company->siren }} @endif
        @if ($company->vat_number) · {{ __('VAT') }} {{ $company->vat_number }} @endif
        <br>
        @if ($company->address) {{ str_replace("\n", ' · ', $company->address) }} @endif
    </div>
</div>

{{-- Page numbering. page_script() runs once per page after layout, so the
     number lands on EVERY page. --}}
<script type="text/php">
if (isset($pdf)) {
    $pdf->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
        $font = $fontMetrics->getFont("Geist", "normal") ?: $fontMetrics->getFont("DejaVu Sans", "normal");
        $size = 7;
        $text = "{{ __('Page') }} {$pageNumber} / {$pageCount}";
        $tw   = $fontMetrics->getTextWidth($text, $font, $size);
        $canvas->text($canvas->get_width() - $tw - 34, $canvas->get_height() - 32, $text, $font, $size, [0.612, 0.643, 0.686]);
    });
}
</script>

</body>
</html>
