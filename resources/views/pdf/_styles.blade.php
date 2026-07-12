<style>
    /*
     * PDF styles — "one accent, fewer boxes, clear hierarchy" redesign.
     * DomPDF doesn't support flex/grid, so the layout is built with <table>
     * elements. Emoji are intentionally avoided — DomPDF can't render colour
     * glyphs reliably; SVG/text marks are used instead.
     *
     * Fonts: Geist (repo /fonts). DomPDF buckets font-weight into
     * normal (<600) and bold (>=600), so we register exactly two faces:
     * normal = Geist-Medium (the brand base weight), bold = Geist-Bold.
     * DejaVu Sans stays as the registered fallback family.
     *
     * Visual language:
     *  - single accent: navy #0e4f79 (+ sky #7dd3fc inside the navy block)
     *  - SECTION titles: navy caps over a gray hairline
     *  - CATEGORY rows:  navy caps on a faint navy tint (#f2f7fb)
     *  - boxes reserved for: totals card, trade-in card, signature areas
     *  - client/contact are open columns — whitespace, not borders
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
     * HEADER — logo/company identity left; document title, reference,
     * issue + validity dates right. Closed by a 1pt navy rule.
     * ─────────────────────────────────────────────────────────────── */
    table.qhead {
        width: 100%;
        border-collapse: collapse;
    }
    table.qhead td { padding: 0 0 4.5mm; vertical-align: top; }
    .qhead-logo {
        max-height: 15mm;
        max-width: 62mm;
    }
    .qhead-name {
        font-size: 14pt;
        font-weight: bold;
        color: #0e4f79;
        letter-spacing: -0.3pt;
    }
    .qhead-sub {
        font-size: 7.5pt;
        color: #6b7280;
        margin-top: 1.4mm;
        line-height: 1.6;
    }
    .qhead-sub strong { color: #374151; }
    .qhead-doctype {
        font-size: 20pt;
        font-weight: bold;
        letter-spacing: 3pt;
        text-transform: uppercase;
        color: #0e4f79;
        text-align: right;
        line-height: 1;
    }
    .qhead-ref {
        font-size: 10pt;
        font-weight: bold;
        color: #1f2937;
        text-align: right;
        margin-top: 2.2mm;
        letter-spacing: 0.4pt;
    }
    .qhead-date {
        font-size: 7.8pt;
        color: #9ca3af;
        text-align: right;
        margin-top: 1mm;
        line-height: 1.55;
    }
    .qhead-strip {
        height: 0.9mm;
        background: #0e4f79;
        margin-bottom: 6mm;
    }

    /* ─────────────────────────────────────────────────────────────────
     * META ROW — Client / contact as OPEN columns (no boxes); a faint
     * vertical hairline separates them.
     * ─────────────────────────────────────────────────────────────── */
    table.qmeta {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6.5mm;
    }
    table.qmeta td {
        width: 50%;
        padding: 0 6mm 0 0;
        vertical-align: top;
    }
    table.qmeta td + td {
        padding: 0 0 0 6mm;
        border-left: 0.2mm solid #e5e7eb;
    }
    .qmeta-label {
        font-size: 6.8pt;
        font-weight: bold;
        letter-spacing: 1.6pt;
        text-transform: uppercase;
        color: #0e4f79;
        margin-bottom: 1.8mm;
    }
    .qmeta-name {
        font-size: 11.5pt;
        font-weight: bold;
        color: #111827;
    }
    .qmeta-detail {
        font-size: 8.2pt;
        color: #6b7280;
        margin-top: 1mm;
        line-height: 1.6;
    }

    /* ─────────────────────────────────────────────────────────────────
     * BOAT HEADLINE — navy side-bar statement
     * ─────────────────────────────────────────────────────────────── */
    table.qboat {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 2mm;
    }
    table.qboat td {
        padding: 1.2mm 0 1.2mm 4mm;
        vertical-align: middle;
        border-left: 1.1mm solid #0e4f79;
    }
    table.qboat td.right {
        border-left: none;
        padding-left: 0;
        text-align: right;
    }
    .qboat-name {
        font-size: 14pt;
        font-weight: bold;
        color: #0e4f79;
        letter-spacing: -0.2pt;
        line-height: 1.15;
    }
    .qboat-variant {
        font-size: 8.8pt;
        color: #6b7280;
        margin-top: 0.8mm;
    }
    .qboat-spec {
        font-size: 6.8pt;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 1.2pt;
    }
    .qboat-spec-value {
        font-size: 9.5pt;
        font-weight: bold;
        color: #374151;
        margin-top: 0.6mm;
    }

    /* ─────────────────────────────────────────────────────────────────
     * SECTION HEADER — navy caps over a gray hairline (distinct from
     * the navy-tinted CATEGORY rows inside the table)
     * ─────────────────────────────────────────────────────────────── */
    .qsection {
        margin: 5mm 0 0;
        padding-bottom: 1.5mm;
        border-bottom: 0.25mm solid #d1d5db;
    }
    .qsection-title {
        font-size: 7.4pt;
        font-weight: bold;
        letter-spacing: 1.6pt;
        text-transform: uppercase;
        color: #0e4f79;
        display: inline;
    }
    .qsection-badge {
        float: right;
        font-size: 7.4pt;
        font-weight: bold;
        color: #16a34a;
        background: #f0fdf4;
        padding: 0.5mm 2.2mm;
        border-radius: 6pt;
    }

    /* ─────────────────────────────────────────────────────────────────
     * INCLUDED EQUIPMENT — two-column list with check marks
     * ─────────────────────────────────────────────────────────────── */
    table.qincluded {
        width: 100%;
        margin: 2.2mm 0 2mm;
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
     * ITEMS TABLE — real column headers, hairline rows, category rows
     * on a faint navy tint.
     * ─────────────────────────────────────────────────────────────── */
    table.qoptions {
        width: 100%;
        margin: 2.2mm 0 3mm;
        border-collapse: collapse;
    }
    table.qoptions tr.head-row td {
        padding: 0 1.5mm 1.4mm;
        font-size: 6.8pt;
        font-weight: bold;
        letter-spacing: 1.2pt;
        text-transform: uppercase;
        color: #9ca3af;
        border-bottom: 0.4mm solid #374151;
    }
    table.qoptions tr.cat-row td {
        padding: 1.7mm 1.5mm;
        font-size: 7.2pt;
        font-weight: bold;
        letter-spacing: 1.3pt;
        text-transform: uppercase;
        color: #0e4f79;
        background: #f2f7fb;
        border-bottom: 0.2mm solid #e2ecf4;
    }
    table.qoptions tr.item-row td {
        padding: 2mm 1.5mm;
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
     * Two columns: conditions (open, hairline rows) on the left,
     * the totals card (the one true "box") on the right.
     * ─────────────────────────────────────────────────────────────── */
    table.qbottom {
        width: 100%;
        margin: 4.5mm 0 5mm;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    table.qbottom td { vertical-align: top; }
    table.qbottom td.left  { width: 52%; padding-right: 7mm; }
    table.qbottom td.right { width: 48%; }

    .qcond-title {
        font-size: 7.4pt;
        font-weight: bold;
        letter-spacing: 1.6pt;
        text-transform: uppercase;
        color: #0e4f79;
        margin-bottom: 2mm;
        padding-bottom: 1.5mm;
        border-bottom: 0.25mm solid #d1d5db;
    }
    table.qcond { width: 100%; border-collapse: collapse; }
    table.qcond td {
        padding: 1.3mm 0;
        font-size: 8.5pt;
        vertical-align: top;
        border-bottom: 0.2mm solid #f3f4f6;
    }
    table.qcond tr:last-child td { border-bottom: none; }
    table.qcond td.label {
        width: 28mm;
        color: #9ca3af;
    }
    table.qcond td.val {
        color: #1f2937;
        font-weight: bold;
    }

    /* Trade-in card (left column, under conditions) */
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
        line-height: 1.55;
    }

    /* Totals card (right column) */
    table.qtotals {
        width: 100%;
        border-collapse: collapse;
    }
    table.qtotals td {
        padding: 2.4mm 4mm;
        font-size: 9pt;
        background: #f8fafc;
        border-bottom: 0.2mm solid #eceff3;
    }
    table.qtotals tr:last-child td { border-bottom: none; }
    /* Keep labels and amounts each on a single line — large totals like
       "383 316,00 €" must never break across two lines. */
    table.qtotals td.label { color: #4b5563; white-space: nowrap; }
    table.qtotals td.val   { text-align: right; font-weight: bold; color: #1f2937; white-space: nowrap; }

    table.qtotals .row-discount td.label { color: #9ca3af; }
    table.qtotals .row-discount td.val   { color: #ea580c; }
    table.qtotals .row-tradein td        { background: #fff7ed; }
    table.qtotals .row-tradein td.label  { color: #ea580c; }
    table.qtotals .row-tradein td.val    { color: #ea580c; }

    table.qtotals .row-ttc td {
        background: #0e4f79;
        padding: 3mm 4mm;
        border-bottom: 0.2mm solid #2a6a94;
    }
    table.qtotals .row-ttc td.label { color: rgba(255,255,255,0.78); font-weight: bold; letter-spacing: 0.3pt; }
    table.qtotals .row-ttc td.val   { color: #ffffff; font-size: 11.5pt; font-weight: bold; }

    table.qtotals .row-net td {
        background: #0e4f79;
        padding: 3.4mm 4mm;
    }
    table.qtotals .row-net td.label { color: rgba(255,255,255,0.65); font-weight: bold; font-size: 8.5pt; letter-spacing: 0.8pt; text-transform: uppercase; }
    table.qtotals .row-net td.val   { color: #7dd3fc; font-size: 14pt; font-weight: bold; }

    /* ─────────────────────────────────────────────────────────────────
     * SIGNATURES — two dashed, unfilled areas
     * ─────────────────────────────────────────────────────────────── */
    table.qsign {
        width: 100%;
        margin: 2mm 0 4mm;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    table.qsign td {
        width: 50%;
        padding: 2mm 4mm 0 0;
        vertical-align: top;
    }
    table.qsign td:last-child { padding-right: 0; padding-left: 4mm; }
    .qsign-title {
        font-size: 7.2pt;
        font-weight: bold;
        letter-spacing: 1.6pt;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 2mm;
    }
    .qsign-area {
        border: 0.25mm dashed #cbd5e1;
        border-radius: 2mm;
        height: 19mm;
        position: relative;
    }
    .qsign-line {
        border-top: 0.2mm solid #e5e7eb;
        position: absolute;
        bottom: 4.5mm;
        left: 4mm;
        right: 4mm;
        font-size: 7.5pt;
        color: #9ca3af;
        padding-top: 1.5mm;
    }
    .qsign-meta {
        font-size: 7.4pt;
        color: #9ca3af;
        margin-top: 1.6mm;
    }

    /* ─────────────────────────────────────────────────────────────────
     * FOOTER — fixed on every page. The page number is drawn onto the
     * canvas by a page_script (see template) so it appears on EVERY page,
     * right-aligned on the same baseline as the legal line.
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
    .qfooter .legal { float: left; max-width: 78%; line-height: 1.55; }
    .qfooter .legal strong { color: #4b5563; }
    .qfooter:after  { content: ''; display: block; clear: both; }
</style>
