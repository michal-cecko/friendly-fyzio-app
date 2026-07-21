@php
    $config ??= [];
    $variant = $config['variant'] ?? 'info';

    [$bg, $textColor, $iconColor] = match ($variant) {
        'success' => ['#DCFCE7', '#22C55E', '#22C55E'],
        'neutral' => ['#FFF8FA', '#666666', '#666666'],
        default => ['#FFF8FA', '#666666', '#ED86A3'],
    };
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;border-collapse:collapse;">
    <tr>
        <td style="background-color:{{ $bg }};border-radius:8px;padding:14px 20px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                <tr>
                    @if(! empty($config['icon']))
                        <td valign="top" style="padding-right:10px;line-height:1;">
                            {!! \App\Support\Icon::render($config['icon'], '', ['style' => "width:16px;height:16px;display:block;color:{$iconColor};"]) !!}
                        </td>
                    @endif
                    <td valign="top" class="e-small" style="font-family:'Open Sans',Arial,sans-serif;font-size:13px;font-weight:600;line-height:1.5;color:{{ $textColor }};">
                        {!! \App\Support\RichText::inline($config['text'] ?? '') !!}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
