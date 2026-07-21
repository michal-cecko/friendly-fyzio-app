@php
    $config ??= [];
    $items = $config['items'] ?? [];
    $title = $config['title'] ?? null;
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;border-collapse:collapse;">
    <tr>
        <td style="background-color:#FFDBE5;border-radius:8px;padding:20px;">
            @if(filled($title))
                <div class="e-text" style="font-family:'Montserrat',Arial,sans-serif;font-size:14px;font-weight:700;color:#1A1A1A;padding-bottom:12px;">{{ $title }}</div>
            @endif

            @foreach($items as $item)
                @php($text = is_array($item) ? ($item['text'] ?? '') : $item)
                <div class="e-small" style="font-family:'Open Sans',Arial,sans-serif;font-size:13px;line-height:1.5;color:#666666;padding:3px 0;">•&nbsp;&nbsp;{{ $text }}</div>
            @endforeach
        </td>
    </tr>
</table>
