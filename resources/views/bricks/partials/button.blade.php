@php
    $btn ??= [];
    $url = \App\Support\LinkResolver::fromConfig($btn, '') ?: '#';
    $style = $btn['style'] ?? 'primary';
    $classes = match ($style) {
        'secondary' => 'bg-neutral-900 text-white hover:bg-neutral-800',
        'outline' => 'border-[1.5px] border-primary bg-white text-primary hover:bg-primary-light',
        'ghost' => 'text-primary hover:bg-primary-light',
        'white' => 'bg-white text-primary-dark hover:bg-white/90',
        default => 'bg-primary text-white hover:bg-primary-dark',
    };
@endphp

<a href="{{ $url }}" class="inline-flex items-center justify-center gap-2.5 rounded-full px-9 py-[18px] font-heading text-base font-semibold transition {{ $classes }}">
    @if(! empty($btn['icon']))
        {!! \App\Support\Icon::render($btn['icon'], 'h-5 w-5') !!}
    @endif
    {{ $btn['text'] ?? '' }}
</a>
