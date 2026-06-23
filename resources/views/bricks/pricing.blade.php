@php
    $config ??= [];
    $rows ??= [];
    $buttons = $config['buttons'] ?? [];
@endphp

<section class="bg-white py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($rows)
            <div class="mx-auto max-w-3xl divide-y divide-line overflow-hidden rounded-2xl border border-line">
                @foreach($rows as $row)
                    <div class="flex items-center justify-between gap-4 bg-white px-6 py-4">
                        <div class="flex flex-col">
                            <span class="font-heading font-semibold text-neutral-900">{{ $row['name'] ?? '' }}</span>
                            @if(! empty($row['note']))
                                <span class="text-sm text-neutral-500">{{ $row['note'] }}</span>
                            @endif
                        </div>
                        @if(! empty($row['price']))
                            <span class="shrink-0 font-heading font-semibold text-primary">{{ $row['price'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($buttons)
            <div class="mt-10 flex flex-wrap justify-center gap-4">
                @foreach($buttons as $btn)
                    @include('bricks.partials.button', ['btn' => $btn])
                @endforeach
            </div>
        @endif
    </div>
</section>
