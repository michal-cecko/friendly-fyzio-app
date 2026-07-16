<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>{{ $data->number }}</title>
    @include('pdf.partials.styles', ['screenPaperHeight' => '148mm', 'printPageSize' => 'A5 landscape'])
</head>
<body>

{{-- Header --}}
<table>
    <tr>
        <td style="vertical-align: top;">
            <div class="logotype">Friendly<span class="accent">Fyzio</span></div>
            <div class="muted" style="font-size: 8px;">{{ $data->supplier['name'] ?? '' }}</div>
        </td>
        <td style="vertical-align: top; text-align: right;">
            <div class="heading pink" style="font-size: 12px; font-weight: 700; letter-spacing: 1.5px;">PŘÍJMOVÝ POKLADNÍ DOKLAD</div>
            <div class="heading mono" style="font-size: 11px; font-weight: 600;">{{ $data->number }}</div>
            <div class="muted" style="font-size: 8px;">Vystaveno: {{ $data->issuedAt }}</div>
        </td>
    </tr>
</table>

<div class="divider-accent" style="margin: 8px 0;"></div>

{{-- Parties --}}
<table style="margin-bottom: 8px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding-right: 16px;">
            <div class="section-label">Vystavil (příjemce platby)</div>
            <div class="heading" style="font-size: 10px; font-weight: 700;">{{ $data->supplier['name'] ?? '' }}</div>
            <div class="muted" style="font-size: 8px;">
                @if(filled($data->supplier['ico'] ?? null)) IČO: {{ $data->supplier['ico'] }} @endif
            </div>
        </td>
        <td style="width: 50%; vertical-align: top;">
            <div class="section-label">Přijato od (plátce)</div>
            <div class="heading" style="font-size: 10px; font-weight: 700;">{{ $data->clientName }}</div>
            @if(filled($data->clientAddress))
                <div class="muted" style="font-size: 8px;">{{ $data->clientAddress }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- Amount box --}}
<div class="box" style="border-color: #ED86A3; margin-bottom: 8px; padding: 8px 12px;">
    <table>
        <tr>
            <td>
                <div class="section-label" style="margin-bottom: 2px;">Přijatá částka</div>
                <div class="muted" style="font-size: 8px; font-style: italic;">slovy: {{ $data->amountInWords }}</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <span class="heading" style="font-size: 16px; font-weight: 700; color: #D4607E;">{{ $data->amountFormatted }}</span>
            </td>
        </tr>
    </table>
</div>

{{-- Payment details --}}
<div class="section-label">Údaje o platbě</div>
<div class="box" style="padding: 8px 10px; margin-bottom: 10px;">
    <table style="font-size: 9px;">
        <tr><td class="muted" style="padding: 2px 12px 2px 0; width: 90px;">Účel platby:</td><td style="font-weight: 600;">{{ $data->purpose }}</td></tr>
        <tr><td class="muted" style="padding: 2px 12px 2px 0;">Způsob úhrady:</td><td>{{ $data->methodLabel }}</td></tr>
        <tr><td class="muted" style="padding: 2px 12px 2px 0;">Datum úhrady:</td><td>{{ $data->receivedAt }}</td></tr>
        @if(filled($data->receivedBy))
            <tr><td class="muted" style="padding: 2px 12px 2px 0;">Přijal:</td><td>{{ $data->receivedBy }}</td></tr>
        @endif
        @if(filled($data->invoiceNumber))
            <tr><td class="muted" style="padding: 2px 12px 2px 0;">Faktura č.:</td><td class="mono" style="font-weight: 600;">{{ $data->invoiceNumber }}</td></tr>
        @endif
    </table>
</div>

{{-- Signatures --}}
<table style="margin: 14px 0 6px;">
    <tr>
        <td style="width: 50%; text-align: center; padding: 0 24px;">
            <div style="border-top: 1px solid #1A1A1A; margin: 22px 24px 4px;"></div>
            <div style="font-size: 8px; font-weight: 600;">Podpis plátce</div>
            <div class="subtle" style="font-size: 8px;">{{ $data->clientName }}</div>
        </td>
        <td style="width: 50%; text-align: center; padding: 0 24px;">
            <div style="border-top: 1px solid #1A1A1A; margin: 22px 24px 4px;"></div>
            <div style="font-size: 8px; font-weight: 600;">Podpis a razítko dodavatele</div>
            @if(filled($data->receivedBy))
                <div class="subtle" style="font-size: 8px;">{{ $data->receivedBy }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="divider" style="margin: 8px 0 6px;"></div>

{{-- Closing note; the supplier info line lives in the running page footer --}}
<div class="heading pink" style="font-size: 9px; font-weight: 600; text-align: center;">Děkujeme za využití služeb FriendlyFyzio!</div>

@if($browserPrint ?? false)
    {{-- Mirrors the Gotenberg running footer for the /nahledy preview + browser print --}}
    <div class="page-footer"><span>{{ $data->footerInfo }}</span></div>
@endif

</body>
</html>
