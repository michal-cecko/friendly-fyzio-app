@php
    $config ??= [];
    $buttons = $config['buttons'] ?? [];
@endphp

@if($buttons)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;border-collapse:collapse;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                    <tr>
                        @foreach($buttons as $btn)
                            @php
                                $url = \App\Support\LinkResolver::fromConfig($btn, '') ?: '#';
                                $style = $btn['style'] ?? 'primary';
                                $accent = ($btn['color'] ?? null) ?: '#ED86A3';
                                $isOutline = in_array($style, ['outline', 'white', 'ghost', 'text'], true);
                                $bg = $isOutline ? '#FFFFFF' : $accent;
                                $fg = $isOutline ? $accent : '#FFFFFF';
                            @endphp
                            <td style="padding:8px;">
                                <a href="{{ $url }}" class="e-btn" style="display:inline-block;background-color:{{ $bg }};color:{{ $fg }};border:1px solid {{ $accent }};border-radius:8px;padding:14px 28px;font-family:'Open Sans',Arial,sans-serif;font-size:15px;font-weight:600;text-decoration:none;">{{ $btn['text'] ?? '' }}</a>
                            </td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endif
