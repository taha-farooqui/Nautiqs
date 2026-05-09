<style>
    /*
     * PDF styles modelled after nautiqs_quote_template_EN.html.
     * DomPDF doesn't support flex/grid, so the original layout is rebuilt
     * with <table> elements. Emoji are intentionally avoided — DomPDF can't
     * render colour glyphs reliably; SVG/text marks are used instead.
     */

    @page { margin: 14mm 10mm 16mm 10mm; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9.5pt;
        color: #1f2937;
        line-height: 1.45;
        margin: 0;
    }

    /* ── Brand palette (mirrors --vars in the HTML template) ─────── */
    .nv-navy   { color: #0d3d5c; }
    .nv-accent { color: #2ab0e8; }
    .nv-green  { color: #16a34a; }
    .nv-orange { color: #ea580c; }

    /* ─────────────────────────────────────────────────────────────────
     * HEADER
     * Two columns inside one navy band: company on the left, doc title +
     * reference on the right. The 3px accent strip below the band is
     * faked with a 0.8mm tall <div>.
     * ─────────────────────────────────────────────────────────────── */
    table.qhead {
        width: 100%;
        background: #0d3d5c;
        color: #ffffff;
        border-collapse: collapse;
    }
    table.qhead td { padding: 7mm 9mm 6mm; vertical-align: top; }
    .qhead-name {
        font-size: 14pt;
        font-weight: bold;
        letter-spacing: -0.3pt;
    }
    .qhead-sub {
        font-size: 8.5pt;
        color: rgba(255,255,255,0.62);
        margin-top: 1mm;
        line-height: 1.5;
    }
    .qhead-doctype {
        font-size: 20pt;
        font-style: italic;
        color: #2ab0e8;
        text-align: right;
        line-height: 1;
    }
    .qhead-ref {
        font-size: 9pt;
        color: rgba(255,255,255,0.6);
        text-align: right;
        margin-top: 2.5mm;
        letter-spacing: 0.7pt;
    }
    .qhead-date {
        font-size: 8.5pt;
        color: rgba(255,255,255,0.5);
        text-align: right;
    }
    .qhead-validity {
        display: inline-block;
        background: rgba(42,176,232,0.18);
        color: #2ab0e8;
        font-size: 8pt;
        padding: 1mm 3mm;
        border-radius: 8pt;
        margin-top: 2mm;
    }
    .qhead-strip {
        height: 0.8mm;
        background: #2ab0e8;
    }

    /* ─────────────────────────────────────────────────────────────────
     * META ROW — Client / Salesperson side by side
     * ─────────────────────────────────────────────────────────────── */
    table.qmeta {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 0.3mm solid #e5e7eb;
    }
    table.qmeta td {
        width: 50%;
        padding: 5mm 9mm;
        vertical-align: top;
    }
    table.qmeta td:first-child {
        border-right: 0.3mm solid #e5e7eb;
    }
    .qmeta-label {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1.2pt;
        text-transform: uppercase;
        color: #2ab0e8;
        margin-bottom: 2mm;
    }
    .qmeta-name {
        font-size: 12pt;
        font-weight: bold;
        color: #111827;
    }
    .qmeta-detail {
        font-size: 9pt;
        color: #6b7280;
        margin-top: 1mm;
        line-height: 1.5;
    }

    /* ─────────────────────────────────────────────────────────────────
     * BOAT BAND — navy stripe under the meta row
     * ─────────────────────────────────────────────────────────────── */
    table.qboat {
        width: 100%;
        background: #0d3d5c;
        color: #ffffff;
        border-collapse: collapse;
    }
    table.qboat td { padding: 5mm 9mm; vertical-align: middle; }
    .qboat-name {
        font-size: 14pt;
        font-weight: bold;
        color: #ffffff;
        letter-spacing: -0.2pt;
    }
    .qboat-variant {
        font-size: 9pt;
        color: rgba(255,255,255,0.6);
        margin-top: 0.7mm;
    }
    .qboat-spec {
        font-size: 8pt;
        color: rgba(255,255,255,0.4);
        text-transform: uppercase;
        letter-spacing: 0.8pt;
    }
    .qboat-spec-value {
        font-size: 9pt;
        color: rgba(255,255,255,0.85);
    }

    /* ─────────────────────────────────────────────────────────────────
     * SECTION HEADER
     * ─────────────────────────────────────────────────────────────── */
    .qsection {
        margin: 4mm 9mm 0;
        padding-bottom: 1.5mm;
        border-bottom: 0.3mm solid #e5e7eb;
    }
    .qsection-title {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1.2pt;
        text-transform: uppercase;
        color: #6b7280;
        display: inline;
    }
    .qsection-badge {
        float: right;
        font-size: 8pt;
        font-weight: 500;
        color: #16a34a;
        background: #f0fdf4;
        padding: 0.5mm 2mm;
        border-radius: 6pt;
    }

    /* ─────────────────────────────────────────────────────────────────
     * INCLUDED EQUIPMENT — two-column list with check marks
     * ─────────────────────────────────────────────────────────────── */
    table.qincluded {
        width: calc(100% - 18mm);
        margin: 2mm 9mm 3mm;
        border-collapse: collapse;
    }
    table.qincluded td {
        width: 50%;
        padding: 1mm 0;
        font-size: 9pt;
        color: #374151;
        vertical-align: top;
    }
    .qcheck {
        display: inline-block;
        width: 3.5mm;
        height: 3.5mm;
        background: #f0fdf4;
        border: 0.2mm solid #86efac;
        border-radius: 50%;
        text-align: center;
        font-size: 7pt;
        font-weight: bold;
        color: #16a34a;
        line-height: 3.4mm;
        margin-right: 1.5mm;
    }

    /* ─────────────────────────────────────────────────────────────────
     * OPTIONS TABLE
     * ─────────────────────────────────────────────────────────────── */
    table.qoptions {
        width: calc(100% - 18mm);
        margin: 2mm 9mm 3mm;
        border-collapse: collapse;
    }
    table.qoptions tr.cat-row td {
        padding: 3mm 2mm 1.5mm;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1pt;
        text-transform: uppercase;
        color: #9ca3af;
        background: #f9fafb;
        border-top: 0.3mm solid #e5e7eb;
    }
    table.qoptions tr.cat-row:first-child td { border-top: none; }
    table.qoptions tr.item-row td {
        padding: 2mm;
        border-bottom: 0.2mm solid #f3f4f6;
        vertical-align: middle;
    }
    .qopt-name { font-size: 9.5pt; color: #1f2937; }
    .qopt-qty   { font-size: 9pt; color: #9ca3af; text-align: center; }
    .qopt-unit  { font-size: 9pt; color: #6b7280; text-align: right; }
    .qopt-total { font-size: 9.5pt; font-weight: bold; color: #1f2937; text-align: right; }
    .qopt-total.discount-applied { color: #ea580c; }
    .qopt-strike {
        text-decoration: line-through;
        color: #d1d5db;
    }
    .qopt-disc-badge {
        display: inline-block;
        font-size: 7.5pt;
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
        width: calc(100% - 18mm);
        margin: 4mm 9mm 6mm;
        border-collapse: collapse;
    }
    table.qbottom td { vertical-align: top; }
    table.qbottom td.left  { width: 58%; padding-right: 6mm; }
    table.qbottom td.right { width: 42%; }

    .qcond-title {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1.2pt;
        text-transform: uppercase;
        color: #9ca3af;
        margin-bottom: 2mm;
    }
    table.qcond { width: 100%; border-collapse: collapse; }
    table.qcond td {
        padding: 0.7mm 0;
        font-size: 9pt;
        vertical-align: top;
    }
    table.qcond td.label {
        width: 32mm;
        color: #9ca3af;
    }
    table.qcond td.val {
        color: #374151;
        font-weight: 500;
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
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.8pt;
        text-transform: uppercase;
        color: #ea580c;
        margin-bottom: 1.5mm;
    }
    .qtradein-detail {
        font-size: 9pt;
        color: #9a3412;
        line-height: 1.5;
    }

    /* Totals box (right column) */
    table.qtotals {
        width: 100%;
        border-collapse: collapse;
        border: 0.2mm solid #e5e7eb;
        border-radius: 2mm;
        background: #f9fafb;
    }
    table.qtotals td {
        padding: 2.4mm 4mm;
        font-size: 9.5pt;
        border-bottom: 0.2mm solid #e5e7eb;
    }
    table.qtotals tr:last-child td { border-bottom: none; }
    table.qtotals td.label { color: #4b5563; }
    table.qtotals td.val   { text-align: right; font-weight: bold; color: #1f2937; }

    table.qtotals .row-white td { background: #ffffff; }
    table.qtotals .row-discount td.label { color: #9ca3af; }
    table.qtotals .row-discount td.val   { color: #ea580c; font-weight: 500; }
    table.qtotals .row-tradein td        { background: #fff7ed; }
    table.qtotals .row-tradein td.label  { color: #ea580c; }
    table.qtotals .row-tradein td.val    { color: #ea580c; }

    table.qtotals .row-ttc td {
        background: #0d3d5c;
        padding: 3mm 4mm;
    }
    table.qtotals .row-ttc td.label { color: rgba(255,255,255,0.7); font-weight: bold; }
    table.qtotals .row-ttc td.val   { color: #ffffff; font-size: 12pt; font-weight: bold; }

    table.qtotals .row-net td {
        background: #0d3d5c;
        padding: 3.5mm 4mm;
    }
    table.qtotals .row-net td.label { color: rgba(255,255,255,0.6); font-weight: bold; font-size: 9pt; }
    table.qtotals .row-net td.val   { color: #2ab0e8; font-size: 14pt; font-weight: bold; }

    /* ─────────────────────────────────────────────────────────────────
     * SIGNATURES (two side-by-side blocks)
     * ─────────────────────────────────────────────────────────────── */
    table.qsign {
        width: calc(100% - 18mm);
        margin: 2mm 9mm 4mm;
        border-collapse: collapse;
        border-top: 0.3mm solid #e5e7eb;
        padding-top: 4mm;
    }
    table.qsign td {
        width: 50%;
        padding: 4mm 4mm 0 0;
        vertical-align: top;
    }
    table.qsign td:last-child { padding-right: 0; padding-left: 4mm; }
    .qsign-title {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1.2pt;
        text-transform: uppercase;
        color: #9ca3af;
        margin-bottom: 2mm;
    }
    .qsign-area {
        border: 0.2mm solid #e5e7eb;
        border-radius: 2mm;
        background: #f9fafb;
        height: 18mm;
        position: relative;
    }
    .qsign-line {
        border-top: 0.2mm solid #d1d5db;
        position: absolute;
        bottom: 5mm;
        left: 4mm;
        right: 4mm;
        font-size: 8pt;
        color: #9ca3af;
        padding-top: 1.5mm;
    }
    .qsign-meta {
        font-size: 8pt;
        color: #9ca3af;
        margin-top: 1.5mm;
    }

    /* ─────────────────────────────────────────────────────────────────
     * FOOTER — runs along the bottom of every page
     * ─────────────────────────────────────────────────────────────── */
    .qfooter {
        position: fixed;
        bottom: -10mm;
        left: 0; right: 0;
        padding: 2mm 9mm 0;
        border-top: 0.2mm solid #e5e7eb;
        font-size: 7.5pt;
        color: #9ca3af;
        background: #f9fafb;
    }
    .qfooter .legal { float: left; max-width: 70%; line-height: 1.5; }
    .qfooter .page  { float: right; }
    .qfooter:after  { content: ''; display: block; clear: both; }
</style>
