<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Faktura {{ $data->number }}</title>
    @include('pdf.partials.styles', ['screenPaperHeight' => '297mm', 'printPageSize' => 'A4 portrait'])
</head>
<body>

{{-- Header: logotype left, document identity right --}}
<table>
    <tr>
        <td style="vertical-align: top;">
            <div class="logotype">Friendly<span class="accent">Fyzio</span></div>
            <div class="muted" style="font-size: 8px;">{{ $data->supplier['name'] ?? '' }}</div>
        </td>
        <td style="vertical-align: top; text-align: right;">
            <div class="heading pink" style="font-size: 18px; font-weight: 700; letter-spacing: 2px;">FAKTURA</div>
            <div class="heading mono" style="font-size: 11px; font-weight: 600;">{{ $data->number }}</div>
            <div class="muted" style="font-size: 8px;">Vystaveno: {{ $data->issuedAt }}</div>
        </td>
    </tr>
</table>

<div class="divider-accent"></div>

{{-- Parties --}}
<table style="margin-bottom: 4px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding-right: 16px;">
            <div class="section-label">Dodavatel</div>
            <div class="heading" style="font-size: 11px; font-weight: 700;">{{ $data->supplier['name'] ?? '' }}</div>
            <div class="muted" style="font-size: 8px;">
                @if(filled($data->supplier['address'] ?? null)) {{ $data->supplier['address'] }}<br> @endif
                @if(filled($data->supplier['ico'] ?? null)) IČO: {{ $data->supplier['ico'] }}<br> @endif
                @if(filled($data->supplier['dic'] ?? null)) DIČ: {{ $data->supplier['dic'] }}<br> @endif
                @if(filled($data->supplier['email'] ?? null)) {{ $data->supplier['email'] }}<br> @endif
                @if(filled($data->supplier['phone'] ?? null)) {{ $data->supplier['phone'] }} @endif
            </div>
        </td>
        <td style="width: 50%; vertical-align: top;">
            <div class="section-label">Odběratel</div>
            <div class="heading" style="font-size: 11px; font-weight: 700;">{{ $data->customer['name'] ?? '' }}</div>
            <div class="muted" style="font-size: 8px;">
                @if(filled($data->customer['address'] ?? null)) {{ $data->customer['address'] }}<br> @endif
                @if(filled($data->customer['ico'] ?? null)) IČO: {{ $data->customer['ico'] }}<br> @endif
                @if(filled($data->customer['dic'] ?? null)) DIČ: {{ $data->customer['dic'] }}<br> @endif
                @if(filled($data->customer['email'] ?? null)) E-mail: {{ $data->customer['email'] }}<br> @endif
                @if(filled($data->customer['phone'] ?? null)) Tel: {{ $data->customer['phone'] }} @endif
            </div>
        </td>
    </tr>
</table>

<div class="divider"></div>

@if(filled($data->textBefore))
    <p style="margin-bottom: 8px;">{{ $data->textBefore }}</p>
@endif

{{-- Items --}}
<table class="items" style="margin-bottom: 10px;">
    <thead>
        <tr>
            <td>Položka</td>
            <td class="center" style="width: 50px;">Počet</td>
            @if($data->showVat)
                <td class="center" style="width: 45px;">DPH</td>
            @endif
            <td class="num" style="width: 80px;">Cena/ks</td>
            <td class="num" style="width: 80px;">Celkem</td>
        </tr>
    </thead>
    <tbody>
        @foreach($data->items as $item)
            <tr>
                <td>
                    <div style="font-weight: 600;">{{ $item['title'] }}</div>
                    @if(filled($item['description']))
                        <div class="muted" style="font-size: 8px;">{{ $item['description'] }}</div>
                    @endif
                </td>
                <td class="center">{{ $item['quantity'] }}</td>
                @if($data->showVat)
                    <td class="center">{{ $item['vatRate'] !== null ? $item['vatRate'].' %' : '—' }}</td>
                @endif
                <td class="num">{{ $item['unitPrice'] }}</td>
                <td class="num" style="font-weight: 600;">{{ $item['total'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Totals --}}
<table style="margin-bottom: 4px;">
    <tr>
        <td></td>
        <td style="text-align: right; white-space: nowrap;">
            <span class="heading" style="font-size: 10px; font-weight: 700; padding-right: 24px;">Celkem k úhradě:</span>
            <span class="heading" style="font-size: 13px; font-weight: 700; color: #D4607E;">{{ $data->totalFormatted }}</span>
        </td>
    </tr>
</table>

@if($data->showVat && $data->vatRows !== [])
    <table style="width: auto; margin-left: auto; margin-bottom: 8px; font-size: 8px;">
        <tr class="muted">
            <td style="padding: 2px 12px 2px 0;">Sazba</td>
            <td class="num" style="padding: 2px 12px 2px 0;">Základ</td>
            <td class="num" style="padding: 2px 12px 2px 0;">DPH</td>
            <td class="num" style="padding: 2px 0;">Celkem</td>
        </tr>
        @foreach($data->vatRows as $row)
            <tr>
                <td style="padding: 2px 12px 2px 0;">{{ $row['rate'] }} %</td>
                <td class="num" style="padding: 2px 12px 2px 0;">{{ $row['base'] }}</td>
                <td class="num" style="padding: 2px 12px 2px 0;">{{ $row['vat'] }}</td>
                <td class="num" style="padding: 2px 0;">{{ $row['total'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if(filled($data->textAfter))
    <p style="margin-bottom: 8px;">{{ $data->textAfter }}</p>
@endif

<div class="divider"></div>

{{-- Payment box — always present; the document never reflects paid state --}}
<div class="section-label" style="font-size: 10px;">Platební údaje</div>
<table>
    <tr>
        <td style="vertical-align: top; padding-right: 12px;">
            <div class="box">
                <table style="font-size: 9px;">
                    @if($data->bankAccount)
                        <tr><td class="muted" style="padding: 2px 12px 2px 0;">Číslo účtu:</td><td style="font-weight: 600;">{{ $data->bankAccount }}</td></tr>
                    @endif
                    @if($data->iban)
                        <tr><td class="muted" style="padding: 2px 12px 2px 0;">IBAN:</td><td style="font-weight: 600;">{{ $data->iban }}</td></tr>
                    @endif
                    @if($data->variableSymbol)
                        <tr><td class="muted" style="padding: 2px 12px 2px 0;">Variabilní symbol:</td><td class="mono" style="font-weight: 600;">{{ $data->variableSymbol }}</td></tr>
                    @endif
                    <tr><td class="muted" style="padding: 2px 12px 2px 0;">Částka:</td><td style="font-weight: 600; color: #D4607E;">{{ $data->totalFormatted }}</td></tr>
                    <tr><td class="muted" style="padding: 2px 12px 2px 0;">Splatnost:</td><td style="font-weight: 600;">{{ $data->dueAt }}</td></tr>
                </table>
            </div>
        </td>
        @if($data->qrDataUri)
            <td style="width: 110px; vertical-align: top; text-align: center;">
                <div class="box" style="width: 90px; height: 90px; padding: 4px; margin: 0 auto;">
                    <img src="{{ $data->qrDataUri }}" alt="QR platba" style="width: 100%; height: 100%;">
                </div>
                <div class="muted" style="font-size: 9px; margin-top: 4px;">QR platba</div>
            </td>
        @endif
    </tr>
</table>

<div class="divider"></div>

{{-- Closing note; the supplier info line lives in the running page footer --}}
@if(filled($data->footerNote))
    <div class="heading pink" style="font-size: 11px; font-weight: 600; text-align: center;">{{ $data->footerNote }}</div>
@endif

@if($browserPrint ?? false)
    {{-- Mirrors the Gotenberg running footer for the /nahledy preview + browser print --}}
    <div class="page-footer"><span>{{ $data->footerInfo }}</span></div>
@endif

</body>
</html>
