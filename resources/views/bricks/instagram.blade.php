@php
    $config ??= [];
    $images = $config['images'] ?? [];
    $ctaUrl = \App\Support\LinkResolver::fromConfig($config, 'cta_');
@endphp

<section class="bg-white py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @forelse($images as $imageId)
                @php($src = \App\Support\Media::url($imageId, '400'))
                <a href="{{ $ctaUrl ?: '#' }}" class="group relative block aspect-square overflow-hidden rounded-xl bg-primary-light">
                    @if($src)
                        <img src="{{ $src }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @endif
                    <span class="absolute inset-0 flex items-center justify-center bg-primary/0 text-white opacity-0 transition group-hover:bg-primary/40 group-hover:opacity-100">
                        <x-lucide name="instagram" class="h-7 w-7" />
                    </span>
                </a>
            @empty
                @for($i = 0; $i < 4; $i++)
                    <div class="aspect-square rounded-xl bg-primary-light"></div>
                @endfor
            @endforelse
        </div>

        @if($ctaUrl && ! empty($config['cta_text']))
            <div class="mt-10 text-center">
                <a href="{{ $ctaUrl }}" class="inline-flex items-center gap-2 rounded-full border-[1.5px] border-primary bg-white px-7 py-3 font-heading text-[15px] font-semibold text-primary transition hover:bg-primary-light">
                    <x-lucide name="instagram" class="h-5 w-5" />
                    {{ $config['cta_text'] }}
                </a>
            </div>
        @endif
    </div>
</section>
