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
    $optionRows = collect($quote->options ?? [])->groupBy(fn ($o) => $o['category'] ?? __('Options'));

    // The person who actually made the quote signs it; company-level
    // salesperson is the fallback for legacy quotes without a creator.
    $spName = $quote->creatorName() ?: ($company->salesperson_name ?? '');
    // Contact email follows the same rule as the name: the teammate who wrote
    // the quote, not the company-wide salesperson (which is seeded to whoever
    // registered the dealership and would otherwise pair B's name with A's
    // address). Company address remains the fallback.
    $spEmail = $quote->creatorEmail() ?: ($company->salesperson_email ?? '');
    $logoSrc = $company->logoDataUri();

    // Snapshot first; falls back to the version's current kit when the
    // snapshot is empty (see Quote::equipmentForDisplay()).
    $equipment = $quote->equipmentForDisplay();

    // Category headings. Internal keys ("Engine") and the generic singular
    // "OPTION" that comes out of the import get a proper localised plural;
    // categories the dealer typed themselves print exactly as entered.
    $catLabel = function ($category) {
        $c = trim((string) $category);
        if (strcasecmp($c, 'Engine') === 0)  return __('Engines');
        if (strcasecmp($c, 'Option') === 0)  return __('Options');
        return $c;
    };

    // The quote's Display mode (HT | TTC) decides how the price columns read.
    // HT prints the raw excl.-VAT figures the calculator works in; TTC scales
    // each line by its own VAT rate so the client sees what they actually pay.
    // The totals box below always shows the full HT → VAT → TTC breakdown, so
    // this only affects the line-item columns.
    $showTtc  = strcasecmp((string) ($quote->display_mode ?? 'TTC'), 'TTC') === 0;
    $quoteVat = (float) ($t['vat_rate'] ?? $quote->vat_rate ?? 0);
    $withVat  = function ($amount, $rate = null) use ($showTtc, $quoteVat) {
        if (! $showTtc) {
            return (float) $amount;
        }
        $r = ($rate === null || $rate === '') ? $quoteVat : (float) $rate;
        return (float) $amount * (1 + $r / 100);
    };
    $colUnit  = $showTtc ? __('Unit price TTC') : __('Unit price HT');
    $colTotal = $showTtc ? __('Total TTC')      : __('Total HT');
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
            <div class="qhead-doctype">{{ __('Quotation') }}</div>
            <div class="qhead-ref">{{ $quote->number }}</div>
            <div class="qhead-date">
                {{ __('Issued on') }} {{ $quote->created_at?->format('d/m/Y') }}
                @if ($quote->expires_at)
                    <br>{{ __('Valid until') }} {{ $quote->expires_at->format('d/m/Y') }}
                @endif
            </div>
        </td>
    </tr>
</table>
<div class="qhead-strip"></div>

{{-- ════════════════════════════ META ROW ═════════════════════════ --}}
<table class="qmeta">
    <tr>
        <td>
            <div class="qmeta-label">{{ __('Client') }}</div>
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
                @if ($spEmail) {{ $spEmail }} @endif
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
            @if (! empty($quote->variant_snapshot['currency']))
                <div class="qboat-spec">{{ __('Currency') }}</div>
                <div class="qboat-spec-value">{{ $quote->variant_snapshot['currency'] }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- ════════════ INCLUDED EQUIPMENT ════════════ --}}
@if (! empty($equipment))
    <div class="qsection">
        <span class="qsection-title">{{ __('Standard included equipment') }}</span>
        <span class="qsection-badge">{{ __('Included in base price') }}</span>
    </div>
    <table class="qincluded">
        @php
            $equip = collect($equipment)->values();
            $rows = $equip->chunk(2);
        @endphp
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $eq)
                    <td><span class="qcheck">&#10003;</span>{{ $eq['label'] ?? '' }}</td>
                @endforeach
                @if ($row->count() < 2) <td></td> @endif
            </tr>
        @endforeach
    </table>
@endif

{{-- ════════════ OPTIONS TABLE ════════════ --}}
<div class="qsection">
    <span class="qsection-title">{{ __('Selected options & services') }}</span>
</div>

<table class="qoptions">
    {{-- Column captions --}}
    <tr class="head-row">
        <td>{{ __('Description') }}</td>
        <td style="width:12mm; text-align:center;">{{ __('Qty') }}</td>
        <td style="width:30mm; text-align:right;">{{ $colUnit }}</td>
        <td style="width:32mm; text-align:right;">{{ $colTotal }}</td>
    </tr>

    {{-- Base boat row --}}
    <tr class="cat-row"><td colspan="4">{{ __('Base boat') }}</td></tr>
    @php
        // Reads "Brand Version" — e.g. "Seagame 200 SF".
        // Avoid "Sun Odyssey 410 — Sun Odyssey 410 — Standard": if the variant
        // name already contains the model name, show the variant alone.
        $bBrand   = $quote->model_snapshot['brand'] ?? '';
        $bModel   = $quote->model_snapshot['name'] ?? '';
        $bVariant = $quote->variant_snapshot['name'] ?? '';
        if ($bVariant === '') {
            $baseLabel = $bModel;
        } elseif ($bModel !== '' && stripos($bVariant, $bModel) !== false) {
            $baseLabel = $bVariant;
        } else {
            $baseLabel = trim($bModel . ' — ' . $bVariant, ' —');
        }
        // Prefix the brand unless it's already part of the label.
        if ($bBrand !== '' && stripos($baseLabel, $bBrand) === false) {
            $baseLabel = trim($bBrand . ' ' . $baseLabel);
        }
    @endphp
    <tr class="item-row">
        <td><span class="qopt-name">{{ $baseLabel }}</span></td>
        <td class="qopt-qty" style="width:12mm;">1</td>
        <td class="qopt-unit" style="width:30mm;">{{ number_format($withVat($t['base_price_gross'] ?? 0), 2, ',', ' ') }} €</td>
        <td class="qopt-total" style="width:32mm;">{{ number_format($withVat($t['base_ht'] ?? 0), 2, ',', ' ') }} €</td>
    </tr>
    @if (($t['boat_discount_pct'] ?? 0) > 0)
        <tr class="item-row">
            <td><span class="qopt-name" style="color:#9ca3af;">{{ __('Boat discount') }} ({{ number_format($t['boat_discount_pct'], 1) }}%)</span></td>
            <td class="qopt-qty"></td>
            <td class="qopt-unit"></td>
            <td class="qopt-total discount-applied">-{{ number_format($withVat($t['boat_discount_amount'] ?? 0), 2, ',', ' ') }} €</td>
        </tr>
    @endif

    {{-- Options grouped by category (see $catLabel above). --}}
    @foreach ($optionRows as $category => $items)
        <tr class="cat-row"><td colspan="4">{{ $catLabel($category) }}</td></tr>
        @foreach ($items as $opt)
            @php
                $itemDisc = (float) ($opt['item_discount_pct'] ?? 0);
                $catDisc  = (float) ($opt['cat_discount_pct'] ?? 0);
                $totalDisc = $itemDisc + $catDisc;
                $unit = $withVat($opt['unit_price'] ?? 0, $opt['line_vat_rate'] ?? null);
                $line = $withVat($opt['line_after_cat'] ?? 0, $opt['line_vat_rate'] ?? null);
            @endphp
            <tr class="item-row">
                <td>
                    <span class="qopt-name">{{ $opt['label'] ?? '' }}</span>
                    @if ($itemDisc > 0)
                        <span class="qopt-disc-badge">-{{ number_format($itemDisc, 0) }}%</span>
                    @endif
                    @if (! empty($opt['description']))
                        <div class="qopt-desc">{!! nl2br(e($opt['description'])) !!}</div>
                    @endif
                </td>
                <td class="qopt-qty">{{ $opt['quantity'] ?? 1 }}</td>
                <td class="qopt-unit">
                    @if ($totalDisc > 0)
                        <span class="qopt-strike">{{ number_format($unit, 2, ',', ' ') }} €</span>
                    @else
                        {{ number_format($unit, 2, ',', ' ') }} €
                    @endif
                </td>
                <td class="qopt-total {{ $totalDisc > 0 ? 'discount-applied' : '' }}">
                    {{ number_format($line, 2, ',', ' ') }} €
                </td>
            </tr>
        @endforeach
    @endforeach

    {{-- Custom items --}}
    @if (! empty($quote->custom_items))
        <tr class="cat-row"><td colspan="4">{{ __('Services') }}</td></tr>
        @foreach ($quote->custom_items as $ci)
            <tr class="item-row">
                <td><span class="qopt-name">{{ $ci['label'] ?? '' }}</span></td>
                <td class="qopt-qty">1</td>
                <td class="qopt-unit">{{ number_format($withVat($ci['amount'] ?? 0), 2, ',', ' ') }} €</td>
                <td class="qopt-total">{{ number_format($withVat($ci['line_after_cat'] ?? $ci['amount'] ?? 0), 2, ',', ' ') }} €</td>
            </tr>
        @endforeach
    @endif
</table>

{{-- ════════════ CONDITIONS + TOTALS ════════════ --}}
<table class="qbottom">
    <tr>
        {{-- Left: terms & conditions --}}
        <td class="left">
            <div class="qcond-title">{{ __('Terms & conditions') }}</div>
            @php
                $termsPayment  = $quote->terms['payment']  ?? null;
                $termsDelivery = $quote->terms['delivery'] ?? null;
                $termsWarranty = $quote->terms['warranty'] ?? null;
                $termsNotes    = $quote->terms['notes']    ?? null;
            @endphp
            <table class="qcond">
                <tr><td class="label">{{ __('Payment') }}</td><td class="val">{{ $termsPayment  ?: '—' }}</td></tr>
                <tr><td class="label">{{ __('Delivery') }}</td><td class="val">{{ $termsDelivery ?: '—' }}</td></tr>
                <tr><td class="label">{{ __('Warranty') }}</td><td class="val">{{ $termsWarranty ?: '—' }}</td></tr>
                <tr><td class="label">{{ __('Notes') }}</td>   <td class="val">{{ $termsNotes    ?: '—' }}</td></tr>
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

        {{-- Right: totals box --}}
        <td class="right">
            @php
                // Every discount granted, itemised, so the client sees the full
                // saving at a glance instead of only the global one. Per-line
                // discounts stay visible in the items table too.
                $dBoat    = (float) ($t['boat_discount_amount'] ?? 0);
                $dOptions = (float) ($t['options_discount_amount'] ?? 0);
                $dGlobal  = (float) ($t['global_discount_amount'] ?? 0);
                // Item/category-level savings, summed off the rows.
                $dItems = collect($t['options_rows'] ?? [])
                    ->sum(fn ($r) => (float) ($r['line_gross'] ?? 0) - (float) ($r['line_after_cat'] ?? 0));
                $dCustom = collect($t['custom_items_rows'] ?? [])
                    ->sum(fn ($r) => (float) ($r['amount'] ?? 0) - (float) ($r['line_after_cat'] ?? 0));
                $dTotal = $dBoat + $dItems + $dOptions + $dCustom + $dGlobal;

                // The stored subtotal is already NET of the boat/option
                // discounts, so listing deductions under it would show the same
                // figure twice and read as if nothing had been deducted. Start
                // from the gross instead: gross - all discounts = total HT.
                $grossHt = (float) ($t['base_price_gross'] ?? 0)
                    + collect($t['options_rows'] ?? [])->sum(fn ($r) => (float) ($r['line_gross'] ?? 0))
                    + collect($t['custom_items_rows'] ?? [])->sum(fn ($r) => (float) ($r['amount'] ?? 0));
            @endphp
            <table class="qtotals">
                <tr class="row-white">
                    {{-- Always excl. VAT here, even when the columns above print
                         TTC — this box is the canonical HT → VAT → TTC ladder. --}}
                    <td class="label">{{ $dTotal > 0.005 ? __('Subtotal before discounts (excl. VAT)') : __('Subtotal excl. VAT') }}</td>
                    <td class="val">{{ number_format($dTotal > 0.005 ? $grossHt : ($t['subtotal_ht'] ?? 0), 2, ',', ' ') }} €</td>
                </tr>
                @if ($dBoat > 0)
                    <tr class="row-discount row-white">
                        <td class="label">{{ __('Boat discount') }} ({{ number_format($t['boat_discount_pct'] ?? 0, 1) }}%)</td>
                        <td class="val">-{{ number_format($dBoat, 2, ',', ' ') }} €</td>
                    </tr>
                @endif
                @if ($dItems + $dCustom > 0.005)
                    <tr class="row-discount row-white">
                        <td class="label">{{ __('Discounts on options') }}</td>
                        <td class="val">-{{ number_format($dItems + $dCustom, 2, ',', ' ') }} €</td>
                    </tr>
                @endif
                @if ($dOptions > 0)
                    <tr class="row-discount row-white">
                        <td class="label">{{ __('Options discount') }} ({{ number_format($t['options_discount_pct'] ?? 0, 1) }}%)</td>
                        <td class="val">-{{ number_format($dOptions, 2, ',', ' ') }} €</td>
                    </tr>
                @endif
                @if ($dGlobal > 0)
                    <tr class="row-discount row-white">
                        <td class="label">{{ __('Global discount') }} ({{ number_format($t['global_discount_pct'] ?? 0, 1) }}%)</td>
                        <td class="val">-{{ number_format($dGlobal, 2, ',', ' ') }} €</td>
                    </tr>
                @endif
                @if ($dTotal > 0.005)
                    <tr class="row-savings">
                        <td class="label">{{ __('Total savings') }}</td>
                        <td class="val">-{{ number_format($dTotal, 2, ',', ' ') }} €</td>
                    </tr>
                @endif
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
            <div class="qsign-title">{{ __('Client acceptance — signature') }}</div>
            <div class="qsign-area">
                <div class="qsign-line">{{ $clientFn ?: __('Client name') }} &nbsp; · &nbsp; {{ __('Date') }}: __ / __ / ____</div>
            </div>
            <div class="qsign-meta">{{ __('By signing, the client accepts all terms and conditions of this quotation.') }}</div>
        </td>
        <td>
            <div class="qsign-title">{{ __('Salesperson signature') }}</div>
            <div class="qsign-area">
                <div class="qsign-line">{{ $spName ?: $company->name }} &nbsp; · &nbsp; {{ __('Date') }}: __ / __ / ____</div>
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
        @if ($company->vat_number) · {{ __('VAT') }} {{ $company->vat_number }} @endif
        <br>
        @if ($company->address) {{ str_replace("\n", ' · ', $company->address) }} @endif
    </div>
</div>

{{-- Page numbering. page_script() runs once per page after layout, so the
     number lands on EVERY page (page_text from an inline script only covers
     the page being rendered when the script node is reached). --}}
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
