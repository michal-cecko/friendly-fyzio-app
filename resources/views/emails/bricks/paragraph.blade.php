@php($config ??= [])

<div style="margin:0 0 24px;font-family:'Open Sans',Arial,sans-serif;font-size:14px;line-height:1.6;color:#666666;">
    {!! \App\Support\RichText::inline($config['text'] ?? '') !!}
</div>
