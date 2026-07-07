@php
    $config ??= [];
    $rows = $config['rows'] ?? [];
    $title = $config['title'] ?? null;
    $variant = $config['variant'] ?? 'default';

    // [background, border css (or null), title colour]
    [$bg, $border, $titleColor] = match ($variant) {
        'muted' => ['#F5F5F5', '1px solid #E5E5E5', '#666666'],
        'success' => ['#DCFCE7', '2px solid #22C55E', '#22C55E'],
        'danger' => ['#F5F5F5', '1px solid #E5E5E5', '#EF4444'],
        default => ['#FFF8FA', null, '#1A1A1A'],
    };
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;border-collapse:collapse;">
    <tr>
        <td style="background-color:{{ $bg }};border-radius:8px;padding:24px;@if($border)border:{{ $border }};@endif">
            @if(filled($title))
                <div style="font-family:'Montserrat',Arial,sans-serif;font-size:16px;font-weight:700;color:{{ $titleColor }};padding-bottom:12px;">{{ $title }}</div>
                <div style="border-top:1px solid #E5E5E5;font-size:0;line-height:0;height:1px;margin-bottom:16px;">&nbsp;</div>
            @endif

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                @foreach($rows as $row)
                    <tr>
                        <td width="130" valign="top" style="font-family:'Open Sans',Arial,sans-serif;font-size:14px;font-weight:600;color:#1A1A1A;padding:6px 12px 6px 0;">{{ $row['label'] ?? '' }}</td>
                        <td valign="top" style="font-family:'Open Sans',Arial,sans-serif;font-size:14px;color:#666666;padding:6px 0;">{{ $row['value'] ?? '' }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
