@php
    $config ??= [];
    $stats = $config['stats'] ?? [];
@endphp

@if($stats)
    <section class="bg-white py-16 lg:py-20">
        <div class="ff-container">
            <div class="grid grid-cols-2 gap-y-10 lg:grid-cols-4 lg:divide-x lg:divide-line">
                @foreach($stats as $stat)
                    <div class="px-4 text-center">
                        <div class="font-heading text-4xl font-extrabold text-primary lg:text-5xl">{{ $stat['value'] ?? '' }}</div>
                        <div class="mt-2 text-sm text-neutral-500">{{ $stat['label'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
