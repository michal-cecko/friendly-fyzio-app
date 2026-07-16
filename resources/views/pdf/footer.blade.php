<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <style>
        /* Gotenberg footers must set their own (tiny) typography or render blank;
           header/footer templates can't load external assets, so no brand fonts here.
           Chromium print templates don't stretch the body either — the row div
           carries an explicit full width to make space-between work. */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #888888;
            margin: 0;
        }

        .row {
            width: 100%;
            box-sizing: border-box;
            padding: 0 {{ $sidePadding ?? '0.4in' }};
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
    </style>
</head>
<body>
    <div class="row">
        <span>{{ $info ?? '' }}</span>
        <span style="white-space: nowrap;">Strana&nbsp;<span class="pageNumber"></span>/<span class="totalPages"></span></span>
    </div>
</body>
</html>
