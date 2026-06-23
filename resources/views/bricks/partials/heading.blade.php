@php($config ??= [])

@if(! empty($config['eyebrow']) || ! empty($config['title']) || ! empty($config['subtitle']))
    <div class="mx-auto mb-12 flex max-w-2xl flex-col items-center gap-3 text-center">
        @if(! empty($config['eyebrow']))
            <p class="font-heading text-sm font-semibold uppercase tracking-[0.12em] text-primary">{{ $config['eyebrow'] }}</p>
        @endif
        @if(! empty($config['title']))
            <h2 class="font-heading text-3xl font-bold text-neutral-900 lg:text-4xl">{!! \App\Support\RichText::inline($config['title']) !!}</h2>
        @endif
        @if(! empty($config['subtitle']))
            <p class="leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($config['subtitle']) !!}</p>
        @endif
        <span class="mt-1 h-[3px] w-[60px] rounded-full bg-primary"></span>
    </div>
@endif
