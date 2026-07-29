@php
    $btn ??= [];
    /** Extra classes the including brick wants on this button (e.g. responsive width). */
    $btnClass ??= '';
    $url = \App\Support\LinkResolver::fromConfig($btn, '') ?: '#';
    $style = $btn['style'] ?? 'primary';
    $color = $btn['color'] ?? null;
    $target = $btn['target'] ?? null;
    $isText = $style === 'text';

    $classes = match ($style) {
        'secondary' => 'bg-neutral-900 text-white hover:bg-neutral-800',
        'soft' => 'bg-primary-light text-primary hover:bg-primary-light/70',
        'outline' => 'border-[1.5px] border-primary bg-white text-primary hover:bg-primary-light',
        'text' => 'text-primary hover:text-primary-dark',
        'ghost' => 'text-primary hover:bg-primary-light',
        'white' => 'bg-white text-primary-dark hover:bg-white/90',
        default => 'bg-primary text-white hover:bg-primary-dark',
    };

    $wrapper = $isText
        ? 'inline-flex items-center gap-1.5 font-heading text-sm font-semibold transition'
        : 'inline-flex items-center justify-center gap-2.5 rounded-full px-9 py-[18px] font-heading text-base font-semibold transition';

    // Custom color overrides the style's default accent. Hover stays class-based.
    $inlineStyle = null;
    if ($color) {
        $inlineStyle = match ($style) {
            'outline' => "border-color: {$color}; color: {$color}",
            'text', 'ghost' => "color: {$color}",
            default => "background-color: {$color}",
        };
    }
@endphp

<a href="{{ $url }}"
   @if($target) target="{{ $target }}" @if($target === '_blank') rel="noopener" @endif @endif
   @if($inlineStyle) style="{{ $inlineStyle }}" @endif
   class="{{ $wrapper }} {{ $classes }} {{ $btnClass }}">
    @if(! $isText && ! empty($btn['icon']))
        {!! \App\Support\Icon::render($btn['icon'], 'h-5 w-5') !!}
    @endif
    {{ $btn['text'] ?? '' }}
    @if($isText && ! empty($btn['icon']))
        {!! \App\Support\Icon::render($btn['icon'], 'h-4 w-4') !!}
    @endif
</a>
