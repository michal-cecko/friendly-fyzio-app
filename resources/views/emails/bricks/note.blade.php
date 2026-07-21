@php($config ??= [])

<div class="e-note" style="margin:0 0 24px;font-family:'Open Sans',Arial,sans-serif;font-size:12px;line-height:1.5;color:#888888;text-align:center;">
    {!! \App\Support\RichText::inline($config['text'] ?? '') !!}
</div>
