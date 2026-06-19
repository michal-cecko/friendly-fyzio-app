@php($config ??= [])

@if(! empty($config['eyebrow']) || ! empty($config['title']) || ! empty($config['subtitle']))
    <div class="mx-auto mb-12 max-w-2xl text-center">
        @if(! empty($config['eyebrow']))
            <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-primary">{{ $config['eyebrow'] }}</p>
        @endif
        @if(! empty($config['title']))
            <h2 class="font-heading text-3xl font-bold text-neutral-900 lg:text-4xl">{{ $config['title'] }}</h2>
        @endif
        @if(! empty($config['subtitle']))
            <p class="mx-auto mt-4 leading-relaxed text-neutral-600">{{ $config['subtitle'] }}</p>
        @endif
    </div>
@endif
