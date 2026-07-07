<style>
    /*
     * PDF styles — "white minimal, navy accents" redesign.
     * DomPDF doesn't support flex/grid, so the layout is built with <table>
     * elements. Emoji are intentionally avoided — DomPDF can't render colour
     * glyphs reliably; SVG/text marks are used instead.
     *
     * Fonts: Geist (repo /fonts). DomPDF buckets font-weight into
     * normal (<600) and bold (>=600), so we register exactly two faces:
     * normal = Geist-Medium (the brand base weight), bold = Geist-Bold.
     * DejaVu Sans stays as the registered fallback family.
     */

    @font-face {
        font-family: 'Geist';
        font-style: normal;
        font-weight: normal;
        src: url('{{ str_replace('\\', '/', base_path('fonts/Geist-Medium.ttf')) }}') format('truetype');
    }
    @font-face {
        font-family: 'Geist';
        font-style: normal;
        font-weight: bold;
        src: url('{{ str_replace('\\', '/', base_path('fonts/Geist-Bold.ttf')) }}') format('truetype');
    }

    @page { margin: 12mm 12mm 20mm 12mm; }

    body {
        font-family: 'Geist', 'DejaVu Sans', sans-serif;
        font-size: 9pt;
        color: #1f2937;
        line-height: 1.5;
        margin: 0;
    }

    /* ── Brand palette ─────────────────────────────────────────────── */
    .nv-navy   { color: #0e4f79; }
    .nv-accent { color: #2ab0e8; }
    .nv-green  { color: #16a34a; }
    .nv-orange { color: #ea580c; }

    /* ─────────────────────────────────────────────────────────────────
     * HEADER — white, logo (or company name) left, document title +
     * reference right, closed by a thin navy rule.
     * ─────────────────────────────────────────────────────────────── */
    table.qhead {
        width: 100%;
        border-collapse: collapse;
    }
    table.qhead td { padding: 0 0 4mm; vertical-align: top; }
    .qhead-logo {
        max-height: 14mm;
        max-width: 62mm;
    }
    .qhead-name {
        font-size: 15pt;
        font-weight: bold;
        color: #0e4f79;
        letter-spacing: -0.3pt;
    }
    .qhead-sub {
        font-size: 7.5pt;
        color: #6b7280;
        margin-top: 1.2mm;
        line-height: 1.55;
    }
    .qhead-doctype {
        font-size: 17pt;
        font-weight: bold;
        letter-spacing: 2.5pt;
        text-transform: uppercase;
        color: #0e4f79;
        text-align: right;
        line-height: 1;
    }
    .qhead-ref {
        font-size: 9.5pt;
        font-weight: bold;
        color: #374151;
        text-align: right;
        margin-top: 2mm;
        letter-spacing: 0.5pt;
    }
    .qhead-date {
        font-size: 8pt;
        color: #9ca3af;
        text-align: right;
        margin-top: 0.5mm;
    }
    .qhead-validity {
        display: inline-block;
        background: #eef5fa;
        color: #0e4f79;
        font-size: 7.5pt;
        font-weight: bold;
        padding: 1mm 2.5mm;
        border-radius: 6pt;
        margin-top: 1.8mm;
    }
    .qhead-strip {
        height: 0.7mm;
        background: #0e4f79;
        margin-bottom: 5mm;
    }

    /* ─────────────────────────────────────────────────────────────────
     * META ROW — Client / contact cards side by side
     * ─────────────────────────────────────────────────────────────── */
    table.qmeta {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 5mm;
    }
    table.qmeta td {
        width: 48%;
        padding: 3.5mm 4.5mm;
        vertical-align: top;
        border: 0.2mm solid #e5e7eb;
        border-radius: 2mm;
    }
    table.qmeta td.spacer {
        width: 4%; border: none; padding: 0;
    }
    .qmeta-label {
        font-size: 6.8pt;
        font-weight: bold;
        letter-spacing: 1.4pt;
        text-transform: uppercase;
        color: #0e4f79;
        margin-bottom: 1.6mm;
    }
    .qmeta-name {
        font-size: 11pt;
        font-weight: bold;
        color: #111827;
    }
    .qmeta-detail {
        font-size: 8.2pt;
        color: #6b7280;
        margin-top: 0.8mm;
        line-height: 1.55;
    }

    /* ─────────────────────────────────────────────────────────────────
     * BOAT LINE — white section, navy model name, hairline rules
     * ─────────────────────────────────────────────────────────────── */
    table.qboat {
        width: 100%;
        border-collapse: collapse;
        border-top: 0.2mm solid #e5e7eb;
        border-bottom: 0.2mm solid #e5e7eb;
        margin-bottom: 3mm;
    }
    table.qboat td { padding: 3.2mm 0.5mm; vertical-align: middle; }
    .qboat-name {
        font-size: 13pt;
        font-weight: bold;
        color: #0e4f79;
        letter-spacing: -0.2pt;
    }
    .qboat-variant {
        font-size: 8.5pt;
        color: #6b7280;
        margin-top: 0.5mm;
    }
    .qboat-spec {
        font-size: 7pt;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 1pt;
    }
    .qboat-spec-value {
        font-size: 9pt;
        font-weight: bold;
        color: #374151;
    }

    /* ─────────────────────────────────────────────────────────────────
     * SECTION HEADER
     * ─────────────────────────────────────────────────────────────── */
    .qsection {
        margin: 4mm 0.5mm 0;
        padding-bottom: 1.4mm;
        border-bottom: 0.35mm solid #0e4f79;
    }
    .qsection-title {
        font-size: 7.2pt;
        font-weight: bold;
        letter-spacing: 1.4pt;
        text-transform: uppercase;
        color: #0e4f79;
        display: inline;
    }
    .qsection-badge {
        float: right;
        font-size: 7.5pt;
        font-weight: bold;
        color: #16a34a;
        background: #f0fdf4;
        padding: 0.5mm 2mm;
        border-radius: 6pt;
    }

    /* ─────────────────────────────────────────────────────────────────
     * INCLUDED EQUIPMENT — two-column list with check marks
     * ─────────────────────────────────────────────────────────────── */
    table.qincluded {
        width: 100%;
        margin: 2mm 0 3mm;
        border-collapse: collapse;
    }
    table.qincluded td {
        width: 50%;
        padding: 1.2mm 0.5mm;
        font-size: 8.5pt;
        color: #374151;
        vertical-align: middle;
    }
    .qcheck {
        display: inline-block;
        width: 3.6mm;
        height: 3.6mm;
        background: #16a34a;
        border: 0.2mm solid #16a34a;
        border-radius: 50%;
        text-align: center;
        font-size: 6.5pt;
        font-weight: bold;
        color: #ffffff;
        line-height: 3.5mm;
        margin-right: 2mm;
        vertical-align: middle;
    }

    /* ─────────────────────────────────────────────────────────────────
     * OPTIONS TABLE — hairline separators, navy category rows (text)
     * ─────────────────────────────────────────────────────────────── */
    table.qoptions {
        width: 100%;
        margin: 2mm 0 3mm;
        border-collapse: collapse;
    }
    table.qoptions tr.cat-row td {
        padding: 3.2mm 1.5mm 1.2mm;
        font-size: 7.2pt;
        font-weight: bold;
        letter-spacing: 1.2pt;
        text-transform: uppercase;
        color: #0e4f79;
        border-bottom: 0.25mm solid #0e4f79;
    }
    table.qoptions tr.item-row td {
        padding: 1.9mm 1.5mm;
        border-bottom: 0.2mm solid #f3f4f6;
        vertical-align: middle;
    }
    .qopt-name { font-size: 9pt; color: #1f2937; }
    .qopt-qty   { font-size: 8.5pt; color: #9ca3af; text-align: center; }
    .qopt-unit  { font-size: 8.5pt; color: #6b7280; text-align: right; white-space: nowrap; }
    .qopt-total { font-size: 9pt; font-weight: bold; color: #1f2937; text-align: right; white-space: nowrap; }
    .qopt-total.discount-applied { color: #ea580c; }
    .qopt-strike {
        text-decoration: line-through;
        color: #d1d5db;
    }
    .qopt-disc-badge {
        display: inline-block;
        font-size: 7pt;
        font-weight: bold;
        color: #ea580c;
        background: #fff7ed;
        border: 0.2mm solid #fed7aa;
        padding: 0.3mm 1.5mm;
        border-radius: 3pt;
        margin-left: 2mm;
    }

    /* ─────────────────────────────────────────────────────────────────
     * TOTALS + CONDITIONS
     * Two columns: conditions on the left, totals card on the right.
     * ─────────────────────────────────────────────────────────────── */
    table.qbottom {
        width: 100%;
        margin: 4mm 0 5mm;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    table.qbottom td { vertical-align: top; }
    table.qbottom td.left  { width: 54%; padding-right: 6mm; }
    table.qbottom td.right { width: 46%; }

    .qcond-title {
        font-size: 7.2pt;
        font-weight: bold;
        letter-spacing: 1.4pt;
        text-transform: uppercase;
        color: #0e4f79;
        margin-bottom: 2mm;
        padding-bottom: 1.2mm;
        border-bottom: 0.35mm solid #0e4f79;
    }
    table.qcond { width: 100%; border-collapse: collapse; }
    table.qcond td {
        padding: 1mm 0;
        font-size: 8.5pt;
        vertical-align: top;
        border-bottom: 0.2mm solid #f3f4f6;
    }
    table.qcond tr:last-child td { border-bottom: none; }
    table.qcond td.label {
        width: 30mm;
        color: #9ca3af;
    }
    table.qcond td.val {
        color: #1f2937;
        font-weight: bold;
    }

    /* Trade-in box (left column under conditions) */
    .qtradein {
        margin-top: 4mm;
        padding: 3mm 4mm;
        background: #fff7ed;
        border: 0.2mm solid #fed7aa;
        border-radius: 2mm;
    }
    .qtradein-title {
        font-size: 7.2pt;
        font-weight: bold;
        letter-spacing: 1pt;
        text-transform: uppercase;
        color: #ea580c;
        margin-bottom: 1.4mm;
    }
    .qtradein-detail {
        font-size: 8.5pt;
        color: #9a3412;
        line-height: 1.5;
    }

    /* Totals box (right column) */
    table.qtotals {
        width: 100%;
        border-collapse: collapse;
        border: 0.25mm solid #e5e7eb;
    }
    table.qtotals td {
        padding: 2.3mm 4mm;
        font-size: 9pt;
        border-bottom: 0.2mm solid #eef0f3;
    }
    table.qtotals tr:last-child td { border-bottom: none; }
    /* Keep labels and amounts each on a single line — large totals like
       "383 316,00 €" must never break across two lines. */
    table.qtotals td.label { color: #4b5563; white-space: nowrap; }
    table.qtotals td.val   { text-align: right; font-weight: bold; color: #1f2937; white-space: nowrap; }

    table.qtotals .row-white td { background: #ffffff; }
    table.qtotals .row-discount td.label { color: #9ca3af; }
    table.qtotals .row-discount td.val   { color: #ea580c; font-weight: bold; }
    table.qtotals .row-tradein td        { background: #fff7ed; }
    table.qtotals .row-tradein td.label  { color: #ea580c; }
    table.qtotals .row-tradein td.val    { color: #ea580c; }

    table.qtotals .row-ttc td {
        background: #0e4f79;
        padding: 2.8mm 4mm;
        border-bottom: 0.2mm solid rgba(255,255,255,0.18);
    }
    table.qtotals .row-ttc td.label { color: rgba(255,255,255,0.75); font-weight: bold; }
    table.qtotals .row-ttc td.val   { color: #ffffff; font-size: 11.5pt; font-weight: bold; }

    table.qtotals .row-net td {
        background: #0e4f79;
        padding: 3.2mm 4mm;
    }
    table.qtotals .row-net td.label { color: rgba(255,255,255,0.65); font-weight: bold; font-size: 8.5pt; }
    table.qtotals .row-net td.val   { color: #7dd3fc; font-size: 13.5pt; font-weight: bold; }

    /* ─────────────────────────────────────────────────────────────────
     * SIGNATURES (two side-by-side blocks)
     * ─────────────────────────────────────────────────────────────── */
    table.qsign {
        width: 100%;
        margin: 2mm 0 4mm;
        border-collapse: collapse;
        border-top: 0.2mm solid #e5e7eb;
        page-break-inside: avoid;
    }
    table.qsign td {
        width: 50%;
        padding: 4mm 4mm 0 0;
        vertical-align: top;
    }
    table.qsign td:last-child { padding-right: 0; padding-left: 4mm; }
    .qsign-title {
        font-size: 7.2pt;
        font-weight: bold;
        letter-spacing: 1.4pt;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 2mm;
    }
    .qsign-area {
        border: 0.2mm solid #e5e7eb;
        border-radius: 2mm;
        background: #fbfcfd;
        height: 18mm;
        position: relative;
    }
    .qsign-line {
        border-top: 0.2mm solid #d1d5db;
        position: absolute;
        bottom: 5mm;
        left: 4mm;
        right: 4mm;
        font-size: 7.5pt;
        color: #9ca3af;
        padding-top: 1.5mm;
    }
    .qsign-meta {
        font-size: 7.5pt;
        color: #9ca3af;
        margin-top: 1.5mm;
    }

    /* ─────────────────────────────────────────────────────────────────
     * FOOTER — runs along the bottom of every page
     * ─────────────────────────────────────────────────────────────── */
    .qfooter {
        position: fixed;
        bottom: -14mm;
        left: 0; right: 0;
        height: 11mm;
        padding: 2mm 0 0;
        border-top: 0.35mm solid #0e4f79;
        font-size: 7pt;
        color: #9ca3af;
        background: #ffffff;
    }
    .qfooter .legal { float: left; max-width: 72%; line-height: 1.55; }
    .qfooter .legal strong { color: #4b5563; }
    .qfooter:after  { content: ''; display: block; clear: both; }
</style>
