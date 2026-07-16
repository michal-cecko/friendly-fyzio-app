<style>
    /* Brand fonts travel as attached woff2 files (Gotenberg has no egress). */
    @font-face { font-family: 'Montserrat'; font-weight: 400; src: url('montserrat-latin-400-normal.woff2') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F, U+20AC; }
    @font-face { font-family: 'Montserrat'; font-weight: 400; src: url('montserrat-latin-ext-400-normal.woff2') format('woff2'); unicode-range: U+0100-024F, U+1E00-1EFF; }
    @font-face { font-family: 'Montserrat'; font-weight: 600; src: url('montserrat-latin-600-normal.woff2') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F, U+20AC; }
    @font-face { font-family: 'Montserrat'; font-weight: 600; src: url('montserrat-latin-ext-600-normal.woff2') format('woff2'); unicode-range: U+0100-024F, U+1E00-1EFF; }
    @font-face { font-family: 'Montserrat'; font-weight: 700; src: url('montserrat-latin-700-normal.woff2') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F, U+20AC; }
    @font-face { font-family: 'Montserrat'; font-weight: 700; src: url('montserrat-latin-ext-700-normal.woff2') format('woff2'); unicode-range: U+0100-024F, U+1E00-1EFF; }
    @font-face { font-family: 'Open Sans'; font-weight: 400; src: url('open-sans-latin-400-normal.woff2') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F, U+20AC; }
    @font-face { font-family: 'Open Sans'; font-weight: 400; src: url('open-sans-latin-ext-400-normal.woff2') format('woff2'); unicode-range: U+0100-024F, U+1E00-1EFF; }
    @font-face { font-family: 'Open Sans'; font-weight: 600; src: url('open-sans-latin-600-normal.woff2') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F, U+20AC; }
    @font-face { font-family: 'Open Sans'; font-weight: 600; src: url('open-sans-latin-ext-600-normal.woff2') format('woff2'); unicode-range: U+0100-024F, U+1E00-1EFF; }
    @font-face { font-family: 'JetBrains Mono'; font-weight: 600; src: url('jetbrains-mono-latin-600-normal.woff2') format('woff2'); }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Open Sans', 'DejaVu Sans', sans-serif;
        font-size: 9px;
        color: #1A1A1A;
        line-height: 1.45;
        -webkit-print-color-adjust: exact;
    }

    .muted { color: #666666; }
    .subtle { color: #888888; }
    .pink { color: #ED86A3; }

    .heading {
        font-family: 'Montserrat', 'DejaVu Sans', sans-serif;
    }

    .mono {
        font-family: 'JetBrains Mono', 'DejaVu Sans Mono', monospace;
    }

    .logotype {
        font-family: 'Montserrat', 'DejaVu Sans', sans-serif;
        font-size: 16px;
        font-weight: 700;
        color: #1A1A1A;
    }

    .logotype .accent { color: #ED86A3; }

    .divider-accent { height: 2px; background: #ED86A3; margin: 12px 0; }
    .divider { height: 1px; background: #E5E5E5; margin: 12px 0; }

    .section-label {
        font-family: 'Montserrat', 'DejaVu Sans', sans-serif;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #ED86A3;
        margin-bottom: 4px;
    }

    table { border-collapse: collapse; width: 100%; }

    table.items thead td {
        background: #2D2D2D;
        color: #FFFFFF;
        font-family: 'Montserrat', 'DejaVu Sans', sans-serif;
        font-size: 8px;
        font-weight: 600;
        padding: 6px 10px;
    }

    table.items thead td:first-child { border-radius: 4px 0 0 0; }
    table.items thead td:last-child { border-radius: 0 4px 0 0; }

    table.items tbody td {
        padding: 6px 10px;
        border-bottom: 1px solid #E5E5E5;
        font-size: 9px;
        vertical-align: top;
    }

    table.items tbody tr:nth-child(even) td { background: #F5F5F5; }

    .num { text-align: right; white-space: nowrap; }
    .center { text-align: center; }

    .box {
        background: #F5F5F5;
        border: 1px solid #E5E5E5;
        border-radius: 8px;
        padding: 12px;
    }

    /* Screen-only paper container: the /nahledy previews stream this exact
       document to the browser, while Gotenberg converts it with print media
       (see GotenbergClient), so these rules never reach the PDF. Both documents
       are 210mm wide; each blade passes its true sheet height so the preview
       card keeps the paper's real proportions — A4 portrait (invoice, 297mm)
       and A5 landscape (receipt, 148mm). */
    @media screen {
        html { background: #E5E5E5; padding: 24px 12px; }

        body {
            width: 210mm;
            min-height: {{ $screenPaperHeight ?? '297mm' }};
            max-width: 100%;
            margin: 0 auto;
            padding: 11mm 10mm 16mm; /* mirrors the Gotenberg page margins */
            background: #FFFFFF;
            border-radius: 2px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .2), 0 8px 24px rgba(0, 0, 0, .12);
        }
    }

    @if($browserPrint ?? false)
    /* Printing the preview straight from the browser (Ctrl+P): a zero @page
       margin leaves the browser no room to draw its own date/title/URL
       header & footer, and the body padding recreates the sheet margins.
       The @page size preselects the right paper per document. Gotenberg
       never receives this block — its margins come from the conversion
       options and its running footer travels as a separate footer.html. */
    @page {
        size: {{ $printPageSize ?? 'A4 portrait' }};
        margin: 0;
    }

    /* Preview counterpart of the Gotenberg running footer (pdf/footer.blade.php). */
    .page-footer {
        display: flex;
        justify-content: space-between;
        font-size: 8px;
        color: #888888;
    }

    @media screen {
        body { position: relative; }

        .page-footer { position: absolute; left: 10mm; right: 10mm; bottom: 7mm; }
    }

    @media print {
        body { padding: 11mm 10mm 16mm; }

        .page-footer { position: fixed; left: 10mm; right: 10mm; bottom: 5mm; }
    }
    @endif
</style>
