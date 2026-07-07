@php
    $config ??= [];
    $categories ??= [];
    $note = $config['note'] ?? null;

    // Drop categories that ended up with no rows so empty tabs never render.
    $categories = array_values(array_filter($categories, fn ($category) => ! empty($category['rows'])));
    $multiple = count($categories) > 1;
@endphp

<section class="bg-white py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($categories)
            <div class="mx-auto max-w-3xl" @if($multiple) x-data="{ tab: 0 }" @endif>
                @if($multiple)
                    <div role="tablist" class="mb-8 flex flex-wrap justify-center gap-x-8 gap-y-2 border-b border-line">
                        @foreach($categories as $i => $category)
                            <button type="button"
                                    role="tab"
                                    @click="tab = {{ $i }}"
                                    :class="tab === {{ $i }} ? 'border-primary text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-900'"
                                    class="-mb-px border-b-2 px-1 pb-3 font-heading text-sm font-semibold transition">
                                {{ $category['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @foreach($categories as $i => $category)
                    <div @if($multiple) x-show="tab === {{ $i }}" x-cloak @endif>
                        @if(! empty($category['heading']))
                            <h3 class="mb-5 font-heading text-xl font-bold text-neutral-900">{{ $category['heading'] }}</h3>
                        @endif

                        <div class="overflow-hidden rounded-2xl border border-line">
                            <div class="hidden grid-cols-[minmax(0,1fr)_auto_120px] gap-6 bg-surface-muted px-6 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-neutral-500 sm:grid">
                                <span>Služba</span>
                                <span class="text-right">Délka</span>
                                <span class="text-right">Cena</span>
                            </div>

                            <div class="divide-y divide-line">
                                @foreach($category['rows'] as $row)
                                    <div class="grid grid-cols-1 gap-1 px-6 py-4 sm:grid-cols-[minmax(0,1fr)_auto_120px] sm:items-center sm:gap-6">
                                        <span class="font-heading font-semibold text-neutral-900">{{ $row['name'] ?? '' }}</span>
                                        @if(! empty($row['note']))
                                            <span class="text-sm text-neutral-500 sm:text-right">{{ $row['note'] }}</span>
                                        @else
                                            <span class="hidden sm:block"></span>
                                        @endif
                                        <span class="font-heading font-semibold text-primary sm:text-right">{{ $row['price'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if(! empty($note))
            <p class="mx-auto mt-6 max-w-3xl text-center text-sm leading-relaxed text-neutral-500">{!! \App\Support\RichText::inline($note) !!}</p>
        @endif
    </div>
</section>
