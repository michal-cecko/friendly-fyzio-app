@php
    $config ??= [];
    $stats = $config['stats'] ?? [];
@endphp

@if($stats)
    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-8 lg:grid-cols-4 lg:divide-x lg:divide-line">
                @foreach($stats as $stat)
                    <div class="text-center">
                        <div class="font-heading text-4xl font-extrabold text-primary lg:text-5xl">{{ $stat['value'] ?? '' }}</div>
                        <div class="mt-2 text-sm font-medium uppercase tracking-wide text-neutral-500">{{ $stat['label'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
