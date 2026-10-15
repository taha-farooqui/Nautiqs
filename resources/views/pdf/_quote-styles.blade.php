<style>
    /*
     * Quote PDF — dealership stationery, not a dashboard.
     *
     * Colour: navy #0e4f79 (brand) and slate ink #1e293b, with their tints
     * (#eef4f8 / #dce8f0 navy, #475569 / #64748b / #94a3b8 / #e2e8f0 /
     * #f1f5f9 ink). Red #c62828 is reserved for discounts, so a saving is
     * the one thing on the page that stands out besides the total.
     *
     * Type does the structure: sentence-case headings, weight and size for
     * hierarchy. Icons only where they label data (contact lines).
     *
     * DomPDF (CSS 2.1): tables for layout. Filled shapes are a box around a
     * plain table or a single colspan cell — adjacent filled cells leave a
     * hairline seam. Geist registers two faces (normal/bold); DomPDF stacks
     * the font's ascent+descent under line-height, so 1.15 reads as ~1.4.
     * Icons are Remix Icon glyphs (fonts/RemixIcon.ttf, Apache 2.0), embedded
     * subset-only.
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
    @font-face {
        font-family: 'RemixIcon';
        font-style: normal;
        font-weight: normal;
        src: url('{{ str_replace('\\', '/', base_path('fonts/RemixIcon.ttf')) }}') format('truetype');
    }

    @page { margin: 14mm 14mm 22mm 14mm; }

    body {
        font-family: 'Geist', 'DejaVu Sans', sans-serif;
        font-size: 8.6pt;
        color: #1e293b;
        line-height: 1.15;
        margin: 0;
    }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; }
    .ic { font-family: 'RemixIcon'; font-weight: normal; color: #0e4f79; }

    /* ── Header ─────────────────────────────────────────────────── */
    .hd-logo { max-height: 17mm; max-width: 62mm; }
    .hd-name { font-size: 15pt; font-weight: bold; color: #0e4f79; }
    .hd-co   { margin-top: 2.5mm; font-size: 7.6pt; color: #64748b; }
    .hd-co strong { color: #1e293b; font-size: 8.4pt; }
    .hd-title {
        text-align: right;
        font-size: 24pt;
        font-weight: bold;
        color: #0e4f79;
        line-height: 1;
    }
    
    /* Information bar: the facts a client looks for first. */
    .info { margin-top: 6mm; background: #eef4f8; border-radius: 2mm; padding: 3mm 4.5mm; }
    .info td { width: 25%; padding-right: 3mm; }
    .info .k { font-size: 7pt; color: #64748b; }
    .info .v { font-size: 9pt; font-weight: bold; color: #0e4f79; margin-top: 0.8mm; }

    /* ── Parties ────────────────────────────────────────────────── */
    table.parties { margin-top: 6mm; }
    table.parties td.col { width: 50%; }
    table.parties td.col + td.col { padding-left: 8mm; }
    .h3 {
        font-size: 9.4pt;
        font-weight: bold;
        color: #0e4f79;
        padding-bottom: 1.6mm;
        margin-bottom: 2.2mm;
        border-bottom: 0.3mm solid #dce8f0;
    }
    .party-name { font-size: 10.5pt; font-weight: bold; color: #1e293b; }
    .party-co   { font-size: 8.4pt; color: #475569; margin-top: 0.5mm; }
    table.lines { margin-top: 1.6mm; width: auto; }
    table.lines td { padding: 0.25mm 0; font-size: 8pt; color: #475569; }
    table.lines td.i { width: 4.5mm; }
    table.lines .ic { font-size: 8.2pt; }

    /* ── Boat ───────────────────────────────────────────────────── */
    .boat { margin-top: 6mm; background: #0e4f79; border-radius: 2mm; padding: 4mm 5.5mm; }
    .boat td { vertical-align: middle; color: #ffffff; }
    .boat td.r { text-align: right; }
    .boat-k    { font-size: 7.4pt; color: #dce8f0; }
    .boat-name { font-size: 15pt; font-weight: bold; color: #ffffff; line-height: 1.1; margin-top: 0.8mm; }
    .boat-var  { font-size: 8.6pt; color: #dce8f0; margin-top: 0.8mm; }
    .boat-v    { font-size: 13pt; font-weight: bold; color: #ffffff; margin-top: 0.8mm; white-space: nowrap; }

    /* ── Tables: the section title is the table's first header row, so
       it can never be left alone at the foot of a page. ───────────── */
    table.sheet { margin-top: 7mm; }
    table.sheet tr.title td {
        padding: 0 0 2.2mm;
        font-size: 10.5pt;
        font-weight: bold;
        color: #0e4f79;
    }
    table.sheet tr.title td .note { float: right; font-size: 7.6pt; font-weight: normal; color: #64748b; padding-top: 1mm; }

    /* Included equipment */
    table.equip tr.title td { border-bottom: 0.3mm solid #dce8f0; }
    table.equip tbody td { width: 50%; padding: 0.8mm 3mm 0.8mm 0; font-size: 8.1pt; color: #334155; }
    table.equip tbody tr.first td { padding-top: 2.4mm; }
    table.equip tbody td table td { width: auto; padding: 0; }
    table.equip tbody td table td.i { width: 4.5mm; }
    table.equip .ic { font-size: 8.4pt; }

    /* Items */
    /* The rule above the captions sits on the one-cell title row: per-cell
       borders break into segments when DomPDF repeats the header. */
    table.items tr.title td { border-bottom: 0.3mm solid #1e293b; }
    table.items tr.cols td {
        padding: 1.8mm 2.5mm;
        font-size: 7.4pt;
        font-weight: bold;
        color: #64748b;
        border-bottom: 0.3mm solid #1e293b;
    }
    table.items td.c-qty   { width: 12mm; text-align: center; }
    table.items td.c-unit  { width: 31mm; text-align: right; }
    table.items td.c-total { width: 31mm; text-align: right; }

    /* Category: a tinted band with a navy edge — one cell, so no seam. The
       edge is on an inner block: a cell border would shift the column. */
    table.items tr.cat td { padding: 0; background: #eef4f8; }
    table.items tr.cat td div {
        padding: 1.9mm 2.5mm 1.9mm 2mm;
        border-left: 1mm solid #0e4f79;
        font-size: 8.4pt;
        font-weight: bold;
        color: #0e4f79;
    }
    table.items tr.space td { height: 2.2mm; padding: 0; }
    /* The cell holding a band + its first item: no padding of its own. */
    table.items td.pack { padding: 0; }
    table.items tr.row td {
        padding: 2mm 2.5mm;
        border-bottom: 0.2mm solid #e2e8f0;
    }
    .it-name { font-size: 8.8pt; color: #1e293b; }
    .it-desc { font-size: 7.6pt; color: #64748b; margin-top: 0.7mm; padding-right: 6mm; }
    /* A kit or propeller, under the engine it goes with. */
    table.items tr.row td.sub { padding-left: 7mm; }
    table.items tr.row td.sub .it-name { color: #475569; }
    .it-qty   { font-size: 8.4pt; color: #475569; text-align: center; }
    .it-unit  { font-size: 8.4pt; color: #475569; text-align: right; white-space: nowrap; }
    .it-total { font-size: 8.8pt; font-weight: bold; color: #1e293b; text-align: right; white-space: nowrap; }
    .it-disc  { font-size: 7.6pt; font-weight: bold; color: #c62828; margin-top: 0.8mm; }

    /* ── Terms + totals ─────────────────────────────────────────── */
    table.bottom { margin-top: 7mm; page-break-inside: avoid; }
    table.bottom td.left  { width: 50%; padding-right: 9mm; }
    table.bottom td.right { width: 50%; }

    table.terms td { padding: 1.3mm 0; font-size: 8.2pt; border-bottom: 0.2mm solid #f1f5f9; }
    table.terms tr.last td { border-bottom: none; }
    table.terms td.k { width: 22mm; color: #64748b; }
    table.terms td.v { color: #1e293b; }
    table.terms td.v.empty { color: #94a3b8; }

    .tradein { margin-top: 4mm; padding: 3mm 3.5mm; border: 0.3mm solid #dce8f0; border-radius: 2mm; font-size: 8.2pt; }
    .tradein .t { font-size: 8.6pt; font-weight: bold; color: #0e4f79; margin-bottom: 1mm; }

    table.totals td { padding: 1.2mm 0; font-size: 8.6pt; }
    table.totals td.k { color: #475569; }
    table.totals td.v { text-align: right; white-space: nowrap; color: #1e293b; }
    table.totals tr.disc td.k { padding-left: 3mm; }
    table.totals tr.disc td.v { color: #c62828; }
    table.totals tr.strong td { font-weight: bold; font-size: 9.4pt; color: #1e293b; padding-top: 1.8mm; }

    .savings { margin: 1.4mm 0 1.6mm; background: #fdecec; border-radius: 1.5mm; padding: 1.7mm 3mm; }
    .savings td { color: #c62828; font-weight: bold; font-size: 8.6pt; }
    .savings td.v { text-align: right; white-space: nowrap; }

    .net { margin-top: 3mm; background: #0e4f79; border-radius: 2mm; padding: 3.4mm 4mm; }
    .net td { vertical-align: middle; }
    .net .k { font-size: 9pt; font-weight: bold; color: #dce8f0; }
    .net .v { font-size: 15pt; font-weight: bold; color: #ffffff; text-align: right; white-space: nowrap; }

    /* ── Signatures ─────────────────────────────────────────────── */
    table.sign { margin-top: 6mm; page-break-inside: avoid; }
    table.sign td.col { width: 50%; }
    table.sign td.col + td.col { padding-left: 8mm; }
    .sign-box { border: 0.3mm solid #e2e8f0; border-radius: 2mm; height: 27mm; position: relative; }
    .sign-t   { position: absolute; top: 2.6mm; left: 3.5mm; font-size: 8.2pt; font-weight: bold; color: #1e293b; }
    .sign-s   { position: absolute; top: 6.8mm; left: 3.5mm; right: 3.5mm; font-size: 7.2pt; color: #94a3b8; }
    .sign-line {
        position: absolute; left: 3.5mm; right: 3.5mm; bottom: 2.6mm;
        border-top: 0.2mm solid #e2e8f0; padding-top: 1.2mm;
        font-size: 7.2pt; color: #64748b;
    }

    /* ── Footer (every page; page number drawn by page_script) ──── */
    .foot {
        position: fixed;
        bottom: -15mm; left: 0; right: 0;
        height: 11mm;
        padding-top: 2.2mm;
        border-top: 0.3mm solid #0e4f79;
        font-size: 6.8pt;
        color: #94a3b8;
    }
    .foot strong { color: #475569; }
    .foot .legal { width: 80%; }
</style>
