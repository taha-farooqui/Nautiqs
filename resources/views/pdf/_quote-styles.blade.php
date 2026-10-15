<style>
    /*
     * Quote PDF. Two colours and their tints, nothing else:
     *   navy  #0e4f79  — brand: headings, icons, the boat band, net payable
     *          #eef4f8 / #dce8f0  tints for fills and rules
     *   ink   #1e293b  — text, with #475569 / #64748b / #94a3b8 for secondary
     *          text and #e2e8f0 / #f1f5f9 for hairlines
     * No green, orange or red: a discount is information, not an alarm.
     *
     * DomPDF is CSS 2.1 — tables for layout, no flex/grid. Weights bucket into
     * normal (<600) and bold, so Geist registers two faces. Icons are Remix
     * Icon glyphs from fonts/RemixIcon.ttf (Apache 2.0), the same set the app
     * uses on screen; the quote is rendered with font subsetting on so only
     * the dozen glyphs used are embedded.
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
        /* DomPDF stacks Geist's ascent+descent under this, so 1.15 reads
           as ~1.4 on paper; anything higher looks double-spaced. */
        line-height: 1.15;
        margin: 0;
    }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; }

    .ic {
        font-family: 'RemixIcon';
        font-weight: normal;
        color: #0e4f79;
    }

    /* ── Header ─────────────────────────────────────────────────── */
    .hd td { padding: 0; }
    .hd-logo { max-height: 16mm; max-width: 60mm; }
    .hd-name { font-size: 15pt; font-weight: bold; color: #0e4f79; letter-spacing: -0.3pt; }
    .hd-co {
        margin-top: 2.5mm;
        font-size: 7.6pt;
        color: #64748b;
    }
    .hd-co strong { color: #1e293b; }
    .hd-kicker {
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 2.4pt;
        text-transform: uppercase;
        color: #0e4f79;
        text-align: right;
    }
    .hd-number {
        font-size: 19pt;
        font-weight: bold;
        color: #1e293b;
        text-align: right;
        letter-spacing: -0.2pt;
        line-height: 1.1;
        margin-top: 1mm;
    }
    table.hd-dates { width: auto; margin: 3mm 0 0 auto; }
    table.hd-dates td {
        padding: 0 0 0 6mm;
        text-align: right;
        white-space: nowrap;
    }
    .hd-dates .k { font-size: 6.6pt; letter-spacing: 1pt; text-transform: uppercase; color: #94a3b8; }
    .hd-dates .v { font-size: 8.6pt; font-weight: bold; color: #1e293b; margin-top: 0.4mm; }
    .hd-dates .v .ic { font-size: 8.4pt; margin-right: 1mm; }
    .hd-rule { height: 0; border-top: 0.25mm solid #e2e8f0; margin: 5mm 0 5mm; }

    /* ── Parties ────────────────────────────────────────────────── */
    table.parties td.col { width: 50%; }
    table.parties td.col + td.col { padding-left: 8mm; }
    .label {
        font-size: 6.6pt;
        font-weight: bold;
        letter-spacing: 1.6pt;
        text-transform: uppercase;
        color: #0e4f79;
        margin-bottom: 2mm;
    }
    .label .ic { font-size: 8pt; letter-spacing: 0; margin-right: 1.2mm; }
    .party-name { font-size: 11pt; font-weight: bold; color: #1e293b; }
    .party-co   { font-size: 8.4pt; color: #475569; margin-top: 0.4mm; }
    table.lines { margin-top: 1.6mm; width: auto; }
    table.lines td { padding: 0.2mm 0; font-size: 8pt; color: #475569; }
    table.lines td.i { width: 4.5mm; }
    table.lines .ic { font-size: 8.4pt; }

    /* ── Boat band ──────────────────────────────────────────────── */
    .boat { margin-top: 6mm; background: #0e4f79; border-radius: 2.5mm; padding: 4mm 6mm; }
    .boat td { vertical-align: middle; color: #ffffff; }
    .boat td.r { text-align: right; }
    .boat-brand { font-size: 6.8pt; font-weight: bold; letter-spacing: 2pt; text-transform: uppercase; color: #dce8f0; }
    .boat-name  { font-size: 15pt; font-weight: bold; color: #ffffff; letter-spacing: -0.2pt; line-height: 1.15; margin-top: 0.8mm; }
    .boat-var   { font-size: 8.6pt; color: #dce8f0; margin-top: 0.8mm; }
    .boat-k     { font-size: 6.6pt; letter-spacing: 1.2pt; text-transform: uppercase; color: #dce8f0; }
    .boat-v     { font-size: 10pt; font-weight: bold; color: #ffffff; margin-top: 0.6mm; white-space: nowrap; }

    /* ── Section titles ─────────────────────────────────────────── */
    .sec {
        margin: 6mm 0 0;
        padding-bottom: 1.6mm;
        border-bottom: 0.25mm solid #e2e8f0;
        font-size: 6.8pt;
        font-weight: bold;
        letter-spacing: 1.6pt;
        text-transform: uppercase;
        color: #0e4f79;
    }
    .sec .ic { font-size: 8.4pt; letter-spacing: 0; margin-right: 1.4mm; }
    .sec .note { float: right; font-weight: normal; letter-spacing: 0.2pt; text-transform: none; color: #64748b; font-size: 7.4pt; }

    /* ── Included equipment ─────────────────────────────────────── */
    table.equip { margin-top: 2mm; }
    table.equip td { width: 50%; padding: 0.7mm 3mm 0.7mm 0; font-size: 8.2pt; color: #334155; }
    table.equip .ic { font-size: 8.6pt; margin-right: 1.6mm; }

    /* ── Items ──────────────────────────────────────────────────── */
    table.items { margin-top: 2mm; }
    table.items thead td {
        padding: 2mm 2mm 2mm;
        font-size: 6.6pt;
        font-weight: bold;
        letter-spacing: 1.2pt;
        text-transform: uppercase;
        color: #94a3b8;
        border-bottom: 0.3mm solid #1e293b;
    }
    table.items td.c-qty   { width: 12mm; text-align: center; }
    table.items td.c-unit  { width: 30mm; text-align: right; }
    table.items td.c-total { width: 32mm; text-align: right; }

    table.items tr.cat td {
        padding: 3.6mm 2mm 1.2mm;
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 1.4pt;
        text-transform: uppercase;
        color: #0e4f79;
        border-bottom: 0.25mm solid #dce8f0;
    }
    table.items tr.row td {
        padding: 1.9mm 2mm;
        border-bottom: 0.2mm solid #f1f5f9;
        vertical-align: top;
    }
    .it-name { font-size: 8.8pt; color: #1e293b; }
    .it-desc { font-size: 7.6pt; color: #64748b; margin-top: 0.6mm; padding-right: 6mm; }
    /* A kit or propeller, under the engine it goes with. */
    table.items tr.row td.sub { padding-left: 6.5mm; }
    table.items tr.row td.sub .it-name { color: #475569; }
    .it-qty   { font-size: 8.4pt; color: #64748b; text-align: center; }
    .it-unit  { font-size: 8.4pt; color: #64748b; text-align: right; white-space: nowrap; }
    .it-total { font-size: 8.8pt; font-weight: bold; color: #1e293b; text-align: right; white-space: nowrap; }
    /* Discount: one quiet line under the name — the rate and what it saves.
       The unit column keeps the list price, the total is already net. */
    .it-disc { font-size: 7.4pt; color: #0e4f79; margin-top: 0.8mm; }
    .it-disc .ic { font-size: 7.6pt; margin-right: 1mm; }
    .it-disc .save { color: #64748b; }

    /* ── Terms + totals ─────────────────────────────────────────── */
    table.bottom { margin-top: 5mm; page-break-inside: avoid; }
    table.bottom td.left  { width: 50%; padding-right: 8mm; }
    table.bottom td.right { width: 50%; }

    table.terms td { padding: 1.3mm 0; border-bottom: 0.2mm solid #f1f5f9; font-size: 8.2pt; vertical-align: top; }
    table.terms tr:last-child td { border-bottom: none; }
    table.terms td.i { width: 5.5mm; }
    table.terms td.i .ic { font-size: 9pt; }
    table.terms td.k { width: 22mm; color: #64748b; }
    table.terms td.v { color: #1e293b; }
    table.terms td.v.empty { color: #94a3b8; }

    .tradein {
        margin-top: 4mm;
        padding: 3mm 3.5mm;
        background: #eef4f8;
        border-radius: 2mm;
        font-size: 8.2pt;
        color: #1e293b;
    }
    .tradein .t { font-size: 6.6pt; font-weight: bold; letter-spacing: 1.4pt; text-transform: uppercase; color: #0e4f79; margin-bottom: 1mm; }
    .tradein .t .ic { font-size: 8.4pt; letter-spacing: 0; margin-right: 1.2mm; }

    table.totals td { padding: 1.2mm 0; font-size: 8.6pt; }
    table.totals td.k { color: #475569; }
    table.totals td.v { text-align: right; white-space: nowrap; color: #1e293b; }
    table.totals tr.disc td.k { color: #64748b; padding-left: 3mm; }
    table.totals tr.disc td.v { color: #0e4f79; }
    /* Filled rows are boxes around a plain table: adjacent cell
       backgrounds leave a hairline seam in DomPDF. */
    .savings { margin: 1.2mm 0; background: #eef4f8; border-radius: 1.5mm; padding: 1.6mm 3mm; }
    .savings td { color: #0e4f79; font-weight: bold; font-size: 8.6pt; }
    .savings td.v { text-align: right; white-space: nowrap; }
    table.totals tr.strong td { font-weight: bold; color: #1e293b; font-size: 9.4pt; padding-top: 1.8mm; }
    .net { margin-top: 3mm; background: #0e4f79; border-radius: 2mm; padding: 3.2mm 4mm; }
    .net td { vertical-align: middle; }
    .net .k { font-size: 7.2pt; font-weight: bold; letter-spacing: 1.6pt; text-transform: uppercase; color: #dce8f0; }
    .net .v { font-size: 15pt; font-weight: bold; color: #ffffff; text-align: right; white-space: nowrap; }

    /* ── Signatures ─────────────────────────────────────────────── */
    table.sign { margin-top: 4.5mm; page-break-inside: avoid; }
    table.sign td.col { width: 50%; }
    table.sign td.col + td.col { padding-left: 8mm; }
    .sign-box {
        border: 0.25mm solid #e2e8f0;
        border-radius: 2mm;
        height: 17mm;
        position: relative;
        margin-top: 1mm;
    }
    .sign-line {
        position: absolute;
        left: 4mm; right: 4mm; bottom: 3mm;
        border-top: 0.2mm solid #e2e8f0;
        padding-top: 1.4mm;
        font-size: 7.2pt;
        color: #94a3b8;
    }
    .sign-meta { font-size: 7.2pt; color: #94a3b8; margin-top: 1.2mm; }

    /* ── Footer (every page; page number drawn by page_script) ──── */
    .foot {
        position: fixed;
        bottom: -15mm; left: 0; right: 0;
        height: 11mm;
        padding-top: 2.2mm;
        border-top: 0.25mm solid #dce8f0;
        font-size: 6.8pt;
        color: #94a3b8;
    }
    .foot strong { color: #475569; }
    .foot .legal { width: 80%; }
</style>
