<style>
    @page { margin: 16mm 14mm 18mm 14mm; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9.5pt;
        color: #111111;
        line-height: 1.45;
        margin: 0;
    }

    /* ── Document header ─────────────────────────────────────────── */
    .doc-header { width: 100%; margin-bottom: 6mm; }
    .doc-header td { vertical-align: top; }
    .doc-header .company-block .company-name {
        font-size: 11pt;
        font-weight: bold;
        margin-bottom: 1mm;
    }
    .doc-header .company-block .meta {
        font-size: 8.5pt;
        line-height: 1.5;
        color: #333333;
    }
    .doc-header .doc-title {
        font-size: 22pt;
        font-weight: bold;
        text-align: right;
        letter-spacing: 1px;
        line-height: 1.0;
    }

    /* ── Reference table (top right small grid) ──────────────────── */
    table.ref {
        width: 100%;
        border-collapse: collapse;
        margin-top: 2mm;
    }
    table.ref th, table.ref td {
        border: 1px solid #000000;
        padding: 1.8mm 3mm;
        font-size: 8.5pt;
    }
    table.ref th {
        background: #eeeeee;
        font-weight: bold;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 8pt;
    }

    /* ── Section labels (CUSTOMER INFO, DESCRIPTION OF WORK, …) ──── */
    .section-label {
        background: #eeeeee;
        border: 1px solid #000000;
        padding: 1.8mm 3mm;
        font-size: 8.5pt;
        font-weight: bold;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-top: 6mm;
        margin-bottom: 0;
    }
    .section-body {
        border: 1px solid #000000;
        border-top: none;
        padding: 3mm;
        font-size: 9pt;
    }
    .section-body p { margin: 0 0 1mm 0; }
    .section-body .small-meta { color: #555555; font-size: 8.5pt; }

    /* ── Two-column block: CUSTOMER INFO | Prepared by ──────────── */
    .two-col-block { width: 100%; border-collapse: collapse; margin-top: 6mm; }
    .two-col-block > tbody > tr > td { vertical-align: top; padding: 0; }
    .two-col-block .col-left  { width: 60%; padding-right: 4mm; }
    .two-col-block .col-right { width: 40%; padding-top: 4mm; padding-left: 6mm; font-style: italic; }
    .two-col-block .col-right strong { font-style: normal; }

    /* ── Itemized table ──────────────────────────────────────────── */
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0;
    }
    table.items thead th {
        background: #eeeeee;
        border: 1px solid #000000;
        padding: 2mm 3mm;
        font-size: 8.5pt;
        font-weight: bold;
        text-align: left;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    table.items thead th.r { text-align: right; }
    table.items thead th.c { text-align: center; }
    table.items tbody td {
        border-left: 1px solid #000000;
        border-right: 1px solid #000000;
        padding: 1.8mm 3mm;
        font-size: 9pt;
        vertical-align: top;
    }
    table.items tbody tr:last-child td {
        border-bottom: 1px solid #000000;
    }
    table.items tbody td .cat-tag {
        font-size: 7.5pt;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #555555;
    }
    .r { text-align: right; }
    .c { text-align: center; }

    /* ── Totals strip (subtotal / discount / total) ──────────────── */
    table.totals {
        width: 100%;
        border-collapse: collapse;
        margin-top: -1px; /* sit flush against items table */
    }
    table.totals td {
        border: 1px solid #000000;
        padding: 2.2mm 3mm;
        font-size: 9pt;
    }
    table.totals .label {
        background: #eeeeee;
        font-weight: bold;
        text-align: right;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 8.5pt;
    }
    table.totals .val {
        text-align: right;
        width: 20%;
    }
    table.totals .grand .label {
        background: #000000;
        color: #ffffff;
        font-size: 9.5pt;
    }
    table.totals .grand .val {
        font-weight: bold;
        font-size: 10.5pt;
    }
    table.totals .filler {
        background: #ffffff;
        border: none;
    }

    /* ── Footnote / legal text block ─────────────────────────────── */
    .footnote {
        margin-top: 6mm;
        padding: 3mm;
        border: 1px solid #000000;
        font-size: 8.5pt;
        line-height: 1.6;
        color: #222222;
    }
    .footnote strong { color: #000000; }

    /* ── Customer acceptance / signature ─────────────────────────── */
    .acceptance { margin-top: 5mm; }
    .acceptance .heading {
        font-size: 8.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 1.5mm;
    }
    .acceptance table { width: 100%; border-collapse: collapse; }
    .acceptance table td { padding: 0 3mm 0 0; font-size: 8.5pt; }
    .sig-line {
        border-bottom: 1px solid #000000;
        height: 8mm;
    }
    .sig-cap {
        font-size: 7.5pt;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #555555;
        padding-top: 1mm;
    }

    /* ── Bottom contact line ─────────────────────────────────────── */
    .bottom-contact {
        margin-top: 6mm;
        text-align: center;
        font-size: 8.5pt;
        color: #333333;
    }

    /* ── Page footer (bottom of every page) ──────────────────────── */
    .footer {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        padding-top: 2mm;
        border-top: 1px solid #000000;
        font-size: 7.5pt;
        color: #555555;
        text-align: center;
    }
    .footer strong { color: #000000; }

    /* ── Helpers ─────────────────────────────────────────────────── */
    .muted { color: #666666; }
    .clearfix:after { content: ''; display: block; clear: both; }
</style>
