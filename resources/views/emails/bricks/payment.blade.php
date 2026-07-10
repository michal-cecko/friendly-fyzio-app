@php
    $config ??= [];
    $title = $config['title'] ?? 'Platební údaje';
    $showQr = $config['show_qr'] ?? true;

    $labelStyle = "font-family:'Open Sans',Arial,sans-serif;font-size:14px;font-weight:600;color:#1A1A1A;padding:6px 12px 6px 0;white-space:nowrap;";
    $valueStyle = "font-family:'Open Sans',Arial,sans-serif;font-size:14px;color:#666666;padding:6px 0;";
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;border-collapse:collapse;">
    <tr>
        <td style="background-color:#FFF8FA;border-radius:8px;padding:24px;text-align:center;">
            @if(filled($title))
                <div style="font-family:'Montserrat',Arial,sans-serif;font-size:16px;font-weight:700;color:#1A1A1A;padding-bottom:16px;">{{ $title }}</div>
            @endif

            @if($showQr)
                <img src="@{{ qr }}" alt="QR platba" width="200" height="200" style="display:block;margin:0 auto 16px;width:200px;height:200px;" />
            @endif

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;border-collapse:collapse;text-align:left;">
                <tr>
                    <td style="{{ $labelStyle }}">Částka:</td>
                    <td style="{{ $valueStyle }}"><strong style="color:#1A1A1A;">@{{ castka }} Kč</strong></td>
                </tr>
                <tr>
                    <td style="{{ $labelStyle }}">Účet:</td>
                    <td style="{{ $valueStyle }}">@{{ iban }}</td>
                </tr>
                <tr>
                    <td style="{{ $labelStyle }}">Variabilní symbol:</td>
                    <td style="{{ $valueStyle }}">@{{ vs }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
