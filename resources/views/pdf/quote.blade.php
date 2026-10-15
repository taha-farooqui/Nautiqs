<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    @include('pdf._quote-styles')
</head>
<body>

@php
    $t        = $quote->totals ?? [];
    $clientFn = trim(($quote->client_snapshot['first_name'] ?? '') . ' ' . ($quote->client_snapshot['last_name'] ?? ''));
    $clientCo = $quote->client_snapshot['company_name'] ?? '';
    $clientEmail = $quote->client_snapshot['email'] ?? '';
    $clientPhone = $quote->client_snapshot['phone'] ?? '';
    $clientAddrLine = $quote->client_snapshot['address_line'] ?? '';
    $clientCityLine = trim(($quote->client_snapshot['postal_code'] ?? '') . ' ' . ($quote->client_snapshot['city'] ?? ''));
    if (! empty($quote->client_snapshot['country'])) {
        $clientCityLine = trim($clientCityLine . ', ' . $quote->client_snapshot['country'], ' ,');
    }
    $clientAddress = trim($clientAddrLine . ($clientAddrLine && $clientCityLine ? ', ' : '') . $clientCityLine);

    // Group options by category for the table
    $optionRows = collect($quote->options ?? [])->groupBy(fn ($o) => $o['category'] ?? __('Options'));

    // The person who actually made the quote signs it; company-level
    // salesperson is the fallback for legacy quotes without a creator. Email
    // and phone follow the same rule (see Quote::creatorEmail()).
    $spName  = $quote->creatorName()  ?: ($company->salesperson_name ?? '');
    $spEmail = $quote->creatorEmail() ?: ($company->salesperson_email ?? '');
    $spPhone = $quote->creatorPhone() ?: ($company->salesperson_phone ?? '');
    $logoSrc = $company->logoDataUri();

    // Snapshot first; falls back to the version's current kit when the
    // snapshot is empty (see Quote::equipmentForDisplay()).
    $equipment = $quote->equipmentForDisplay();

    // Category headings. Internal keys get a proper localised plural;
    // categories the dealer typed themselves print exactly as entered.
    $catLabel = function ($category) {
        $c = trim((string) $category);
        if (strcasecmp($c, 'Engine') === 0)    return __('Engines');
        if (strcasecmp($c, 'Accessory') === 0) return __('Accessories');
        if (strcasecmp($c, 'Option') === 0)    return __('Options');
        return $c;
    };

    // Display mode (HT | TTC) decides how the line columns read; the totals
    // box always shows the full HT → VAT → TTC ladder.
    $showTtc  = strcasecmp((string) ($quote->display_mode ?? 'TTC'), 'TTC') === 0;
    $quoteVat = (float) ($t['vat_rate'] ?? $quote->vat_rate ?? 0);
    $withVat  = function ($amount, $rate = null) use ($showTtc, $quoteVat) {
        if (! $showTtc) {
            return (float) $amount;
        }
        $r = ($rate === null || $rate === '') ? $quoteVat : (float) $rate;
        return (float) $amount * (1 + $r / 100);
    };
    $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ''), '0'), ',') . ' %';

    // A discount entered as a flat euro amount prints as the amount alone —
    // appending the percentage it works out to reads as two discounts.
    $discSuffix = function (string $key) use ($t, $pct) {
        if (($t[$key . '_discount_mode'] ?? 'pct') === 'eur') {
            return '';
        }
        return ' ' . $pct($t[$key . '_discount_pct'] ?? 0);
    };
    // A euro discount comes straight off the price, so it prints as agreed; a
    // percentage is proportional and scales with the display basis.
    $discShown = function (string $key) use ($t, $withVat) {
        $amount = (float) ($t[$key . '_discount_amount'] ?? 0);
        return ($t[$key . '_discount_mode'] ?? 'pct') === 'eur' ? $amount : $withVat($amount);
    };
    $colUnit  = $showTtc ? __('Unit price TTC') : __('Unit price HT');
    $colTotal = $showTtc ? __('Total TTC')      : __('Total HT');

    // Base boat label: "Brand Version", without repeating the model name
    // when the version already carries it.
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
    if ($bBrand !== '' && stripos($baseLabel, $bBrand) === false) {
        $baseLabel = trim($bBrand . ' ' . $baseLabel);
    }

    // Custom items: the priced rows from the totals when present (they carry
    // the net figure), the raw inputs for older quotes.
    $customRows = ! empty($t['custom_items_rows']) ? $t['custom_items_rows'] : ($quote->custom_items ?? []);

    // Remix Icon code points (fonts/RemixIcon.ttf) — contact lines only.
    $icon = fn (string $hex) => '<span class="ic">&#x' . $hex . ';</span>';
    $I = ['phone' => 'efec', 'mail' => 'eef6', 'pin' => 'ef14', 'check' => 'eb7b'];

    // Every discount, for the totals column.
    $dBoat    = (float) ($t['boat_discount_amount'] ?? 0);
    $dOptions = (float) ($t['options_discount_amount'] ?? 0);
    $dEngines = (float) ($t['engines_discount_amount'] ?? 0);
    $dGlobal  = (float) ($t['global_discount_amount'] ?? 0);
    $dItems   = collect($t['options_rows'] ?? [])
        ->sum(fn ($r) => (float) ($r['line_gross'] ?? 0) - (float) ($r['line_after_cat'] ?? 0));
    $dCustom  = collect($t['custom_items_rows'] ?? [])
        ->sum(fn ($r) => (float) ($r['amount'] ?? 0) - (float) ($r['line_after_cat'] ?? 0));
    $dTotal   = $dBoat + $dItems + $dOptions + $dEngines + $dCustom + $dGlobal;
    // The stored subtotal is already net of line discounts; start from the
    // gross so gross − every discount = total HT, line by line.
    $grossHt = (float) ($t['base_price_gross'] ?? 0)
        + collect($t['options_rows'] ?? [])->sum(fn ($r) => (float) ($r['line_gross'] ?? 0))
        + collect($t['custom_items_rows'] ?? [])->sum(fn ($r) => (float) ($r['amount'] ?? 0));
    // One VAT line per rate when lines carry different rates.
    $vatBands = collect($t['vat_breakdown'] ?? [])->filter(fn ($taxable) => $taxable > 0.005);

    $terms = [
        [__('Payment'),  $quote->terms['payment']  ?? null],
        [__('Delivery'), $quote->terms['delivery'] ?? null],
        [__('Warranty'), $quote->terms['warranty'] ?? null],
        [__('Notes'),    $quote->terms['notes']    ?? null],
    ];
@endphp

{{-- ═══════════════ HEADER ═══════════════ --}}
<table>
    <tr>
        <td style="width:60%;">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" class="hd-logo" alt="{{ $company->name }}" />
            @else
                <div class="hd-name">{{ $company->name ?? 'Nautiqs' }}</div>
            @endif
            <div class="hd-co">
                @if ($logoSrc && $company->name) <strong>{{ $company->name }}</strong><br> @endif
                @if ($company->address) {!! nl2br(e($company->address)) !!}<br> @endif
                @if ($company->salesperson_phone) {{ $company->salesperson_phone }} @endif
                @if ($company->salesperson_phone && $company->salesperson_email) &nbsp;·&nbsp; @endif
                @if ($company->salesperson_email) {{ $company->salesperson_email }} @endif
            </div>
        </td>
        <td style="width:40%;">
            <div class="hd-title">{{ __('Quotation') }}</div>
        </td>
    </tr>
</table>

<div class="info">
    <table>
        <tr>
            <td>
                <div class="k">{{ __('Reference') }}</div>
                <div class="v">{{ $quote->number }}</div>
            </td>
            <td>
                <div class="k">{{ __('Issued on') }}</div>
                <div class="v">{{ $quote->created_at?->format('d/m/Y') }}</div>
            </td>
            <td>
                <div class="k">{{ __('Valid until') }}</div>
                <div class="v">{{ $quote->expires_at ? $quote->expires_at->format('d/m/Y') : '—' }}</div>
            </td>
            <td>
                <div class="k">{{ __('Your contact') }}</div>
                <div class="v">{{ $spName ?: $company->name }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════ PARTIES ═══════════════ --}}
<table class="parties">
    <tr>
        <td class="col">
            <div class="h3">{{ __('Client') }}</div>
            <div class="party-name">{{ $clientFn ?: __('Guest') }}</div>
            @if ($clientCo) <div class="party-co">{{ $clientCo }}</div> @endif
            <table class="lines">
                @if ($clientEmail) <tr><td class="i">{!! $icon($I['mail']) !!}</td><td>{{ $clientEmail }}</td></tr> @endif
                @if ($clientPhone) <tr><td class="i">{!! $icon($I['phone']) !!}</td><td>{{ $clientPhone }}</td></tr> @endif
                @if ($clientAddress) <tr><td class="i">{!! $icon($I['pin']) !!}</td><td>{{ $clientAddress }}</td></tr> @endif
            </table>
        </td>
        <td class="col">
            <div class="h3">{{ __('Your contact') }}</div>
            <div class="party-name">{{ $spName ?: $company->name }}</div>
            @if ($spName && $company->name) <div class="party-co">{{ $company->name }}</div> @endif
            <table class="lines">
                @if ($spPhone) <tr><td class="i">{!! $icon($I['phone']) !!}</td><td>{{ $spPhone }}</td></tr> @endif
                @if ($spEmail) <tr><td class="i">{!! $icon($I['mail']) !!}</td><td>{{ $spEmail }}</td></tr> @endif
            </table>
        </td>
    </tr>
</table>

{{-- ═══════════════ BOAT ═══════════════ --}}
<div class="boat">
    <table>
        <tr>
            <td style="width:66%;">
                <div class="boat-k">{{ $bBrand ?: __('Boat') }}</div>
                <div class="boat-name">{{ $bModel ?: $bVariant }}</div>
                @if ($bVariant && $bVariant !== $bModel) <div class="boat-var">{{ $bVariant }}</div> @endif
            </td>
            <td class="r" style="width:34%;">
                <div class="boat-k">{{ $showTtc ? __('Net payable') : __('Total excl. VAT') }}</div>
                <div class="boat-v">{{ $eur($showTtc ? ($t['net_payable'] ?? $t['total_ttc'] ?? 0) : ($t['total_ht'] ?? 0)) }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════ INCLUDED EQUIPMENT ═══════════════ --}}
@if (! empty($equipment))
    <table class="sheet equip">
        <thead>
            <tr class="title">
                <td colspan="2">{{ __('Standard included equipment') }} <span class="note">{{ __('Included in base price') }}</span></td>
            </tr>
        </thead>
        <tbody>
            @foreach (collect($equipment)->values()->chunk(2) as $i => $pair)
                <tr class="{{ $loop->first ? 'first' : '' }}">
                    @foreach ($pair as $eq)
                        {{-- Icon and text in their own cells, so a long label
                             wraps under itself, not under the check. --}}
                        <td><table><tr><td class="i">{!! $icon($I['check']) !!}</td><td>{{ $eq['label'] ?? '' }}</td></tr></table></td>
                    @endforeach
                    @if ($pair->count() < 2) <td></td> @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- ═══════════════ ITEMS ═══════════════
     DomPDF never splits a table row across pages, but it will happily leave a
     heading row at the foot of a page and push the next row over (a thead
     doesn't help either). So each category band is packed into one row
     together with its first item, via an inner table with the same column
     widths — and the section title and captions ride in the very first one. --}}
@php
    // [label, rows] in print order.
    $groups = [];
    $groups[] = [__('Base boat'), [[
        'name' => $baseLabel, 'desc' => null, 'sub' => false,
        'disc' => $dBoat > 0 ? __('Discount') . $discSuffix('boat') . ' · −' . $eur($discShown('boat')) : null,
        'qty' => 1, 'unit' => $withVat($t['base_price_gross'] ?? 0), 'total' => $withVat($t['base_ht'] ?? 0),
    ]]];
    foreach ($optionRows as $category => $items) {
        $rows = [];
        foreach ($items as $opt) {
            $rate = $opt['line_vat_rate'] ?? null;
            // Line + category discount, compounded as the calculator applies them.
            $effPct = (1 - (1 - (float) ($opt['item_discount_pct'] ?? 0) / 100)
                         * (1 - (float) ($opt['cat_discount_pct'] ?? 0) / 100)) * 100;
            $saved = $withVat((float) ($opt['line_gross'] ?? 0) - (float) ($opt['line_after_cat'] ?? 0), $rate);
            $rows[] = [
                'name' => $opt['label'] ?? '', 'desc' => $opt['description'] ?? null,
                'sub'  => ($opt['source'] ?? null) === 'accessory',
                'disc' => $effPct > 0.05 ? __('Discount') . ' ' . $pct($effPct) . ($saved > 0.005 ? ' · −' . $eur($saved) : '') : null,
                'qty'  => $opt['quantity'] ?? 1,
                'unit' => $withVat($opt['unit_price'] ?? 0, $rate),
                'total'=> $withVat($opt['line_after_cat'] ?? 0, $rate),
            ];
        }
        $groups[] = [$catLabel($category), $rows];
    }
    if (! empty($customRows)) {
        $rows = [];
        foreach ($customRows as $ci) {
            $amount = (float) ($ci['amount'] ?? 0);
            $net    = (float) ($ci['line_after_cat'] ?? $amount);
            $ciPct  = $amount > 0 ? (1 - $net / $amount) * 100 : 0;
            $rows[] = [
                'name' => $ci['label'] ?? '', 'desc' => null, 'sub' => false,
                'disc' => $ciPct > 0.05 ? __('Discount') . ' ' . $pct($ciPct) . ' · −' . $eur($withVat($amount - $net)) : null,
                'qty' => 1, 'unit' => $withVat($amount), 'total' => $withVat($net),
            ];
        }
        $groups[] = [__('Services'), $rows];
    }

    // The four cells of an item line.
    $cells = function (array $r) use ($eur) {
        $html = '<td class="' . ($r['sub'] ? 'sub' : '') . '"><div class="it-name">' . e($r['name']) . '</div>';
        if (! empty($r['desc'])) $html .= '<div class="it-desc">' . nl2br(e($r['desc'])) . '</div>';
        if ($r['disc'])          $html .= '<div class="it-disc">' . e($r['disc']) . '</div>';
        return $html . '</td>'
            . '<td class="c-qty it-qty">' . e($r['qty']) . '</td>'
            . '<td class="c-unit it-unit">' . e($eur($r['unit'])) . '</td>'
            . '<td class="c-total it-total">' . e($eur($r['total'])) . '</td>';
    };
@endphp

<table class="items sheet">
    @foreach ($groups as [$label, $rows])
        {{-- Band + first item: one unbreakable row. --}}
        <tr>
            <td colspan="4" class="pack">
                <table class="items">
                    @if ($loop->first)
                        <tr class="title"><td colspan="4">{{ __('Selected options & services') }}</td></tr>
                        <tr class="cols">
                            <td>{{ __('Description') }}</td>
                            <td class="c-qty">{{ __('Qty') }}</td>
                            <td class="c-unit">{{ $colUnit }}</td>
                            <td class="c-total">{{ $colTotal }}</td>
                        </tr>
                    @endif
                    <tr class="space"><td colspan="4"></td></tr>
                    <tr class="cat"><td colspan="4"><div>{{ $label }}</div></td></tr>
                    <tr class="row">{!! $cells($rows[0]) !!}</tr>
                </table>
            </td>
        </tr>
        @foreach (array_slice($rows, 1) as $r)
            <tr class="row">{!! $cells($r) !!}</tr>
        @endforeach
    @endforeach
</table>

{{-- ═══════════════ TERMS + TOTALS ═══════════════ --}}
<table class="bottom">
    <tr>
        <td class="left">
            <div class="h3">{{ __('Terms & conditions') }}</div>
            <table class="terms">
                @foreach ($terms as [$k, $v])
                    <tr class="{{ $loop->last ? 'last' : '' }}">
                        <td class="k">{{ $k }}</td>
                        <td class="v {{ $v ? '' : 'empty' }}">{{ $v ?: '—' }}</td>
                    </tr>
                @endforeach
            </table>

            @if (! empty($quote->trade_in) && (($quote->trade_in['value'] ?? 0) > 0))
                <div class="tradein">
                    <div class="t">{{ __('Trade-in included') }}</div>
                    @if (! empty($quote->trade_in['description'])) {{ $quote->trade_in['description'] }}<br> @endif
                    {{ __('Trade-in value') }} : <strong>{{ $eur($quote->trade_in['value']) }}</strong>
                </div>
            @endif
        </td>

        <td class="right">
            <div class="h3">{{ __('Summary') }}</div>
            {{-- Discounts first, from the gross down. Without any, the subtotal
                 would only repeat the total HT, so the block is left out. --}}
            @if ($dTotal > 0.005)
                <table class="totals">
                    <tr><td class="k">{{ __('Subtotal before discounts (excl. VAT)') }}</td><td class="v">{{ $eur($grossHt) }}</td></tr>
                    @if ($dBoat > 0)
                        <tr class="disc"><td class="k">{{ __('Boat discount') }}{{ $discSuffix('boat') }}</td><td class="v">−{{ $eur($dBoat) }}</td></tr>
                    @endif
                    @if ($dItems + $dCustom > 0.005)
                        <tr class="disc"><td class="k">{{ __('Line discounts') }}</td><td class="v">−{{ $eur($dItems + $dCustom) }}</td></tr>
                    @endif
                    @if ($dOptions > 0)
                        <tr class="disc"><td class="k">{{ __('Options discount') }}{{ $discSuffix('options') }}</td><td class="v">−{{ $eur($dOptions) }}</td></tr>
                    @endif
                    @if ($dEngines > 0)
                        <tr class="disc"><td class="k">{{ __('Engines discount') }}{{ $discSuffix('engines') }}</td><td class="v">−{{ $eur($dEngines) }}</td></tr>
                    @endif
                    @if ($dGlobal > 0)
                        <tr class="disc"><td class="k">{{ __('Global discount') }}{{ $discSuffix('global') }}</td><td class="v">−{{ $eur($dGlobal) }}</td></tr>
                    @endif
                </table>
                <div class="savings">
                    <table><tr><td>{{ __('Total savings') }}</td><td class="v">−{{ $eur($dTotal) }}</td></tr></table>
                </div>
            @endif
            <table class="totals">
                <tr><td class="k">{{ __('Total excl. VAT') }}</td><td class="v">{{ $eur($t['total_ht'] ?? 0) }}</td></tr>
                @if ($vatBands->count() > 1)
                    @foreach ($vatBands as $rate => $taxable)
                        <tr><td class="k">{{ __('VAT') }} {{ $pct($rate) }}</td><td class="v">{{ $eur($taxable * (float) $rate / 100) }}</td></tr>
                    @endforeach
                @else
                    <tr><td class="k">{{ __('VAT') }} {{ $pct($t['vat_rate'] ?? 20) }}</td><td class="v">{{ $eur($t['vat_amount'] ?? 0) }}</td></tr>
                @endif
                <tr class="strong"><td class="k">{{ __('Total incl. VAT') }}</td><td class="v">{{ $eur($t['total_ttc'] ?? 0) }}</td></tr>
                @if (($t['trade_in_deduction'] ?? 0) > 0)
                    <tr class="disc"><td class="k">{{ __('Trade-in deduction') }}</td><td class="v">−{{ $eur($t['trade_in_deduction']) }}</td></tr>
                @endif
            </table>
            <div class="net">
                <table>
                    <tr>
                        <td class="k">{{ __('Net payable') }}</td>
                        <td class="v">{{ $eur($t['net_payable'] ?? $t['total_ttc'] ?? 0) }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ═══════════════ SIGNATURES ═══════════════ --}}
<table class="sign">
    <tr>
        <td class="col">
            <div class="sign-box">
                <div class="sign-t">{{ __('Client acceptance — signature') }}</div>
                <div class="sign-s">{{ __('By signing, the client accepts all terms and conditions of this quotation.') }}</div>
                <div class="sign-line">{{ $clientFn ?: __('Client name') }} &nbsp;·&nbsp; {{ __('Date') }} : ___ / ___ / ______</div>
            </div>
        </td>
        <td class="col">
            <div class="sign-box">
                <div class="sign-t">{{ __('Salesperson signature') }}</div>
                <div class="sign-s">{{ $company->name }}</div>
                <div class="sign-line">{{ $spName ?: $company->name }} &nbsp;·&nbsp; {{ __('Date') }} : ___ / ___ / ______</div>
            </div>
        </td>
    </tr>
</table>

{{-- ═══════════════ FOOTER (every page) ═══════════════ --}}
<div class="foot">
    <div class="legal">
        <strong>{{ $company->name }}</strong>
        @if ($company->legal_form) · {{ $company->legal_form }} @endif
        @if ($company->siren) · SIREN {{ $company->siren }} @endif
        @if ($company->vat_number) · {{ __('VAT') }} {{ $company->vat_number }} @endif
        @if ($company->address) <br>{{ str_replace("\n", ' · ', $company->address) }} @endif
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
        $canvas->text($canvas->get_width() - $tw - 40, $canvas->get_height() - 40, $text, $font, $size, [0.58, 0.64, 0.72]);
    });
}
</script>

</body>
</html>
