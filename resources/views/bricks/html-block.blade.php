@php($config ??= [])
@php($html = $config['html'] ?? '')
@php($contained = $config['contained'] ?? true)

@if (filled($html))
    <section class="py-12 lg:py-16">
        @if ($contained)
            <div class="ff-container">
                {!! $html !!}
            </div>
        @else
            {!! $html !!}
        @endif
    </section>
@endif
