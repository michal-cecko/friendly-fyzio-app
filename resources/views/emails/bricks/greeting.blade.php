@php($config ??= [])

<div class="e-greeting" style="margin:0 0 24px;font-family:'Open Sans',Arial,sans-serif;font-size:18px;font-weight:600;line-height:1.5;color:#1A1A1A;">
    {!! \App\Support\RichText::inline($config['text'] ?? '') !!}
</div>
