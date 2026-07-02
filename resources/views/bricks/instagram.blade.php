@php
    $config ??= [];
    // Synced posts come from a connected Instagram account. When none is configured
    // we fall back to the legacy manually-picked images so existing pages keep working.
    $posts ??= collect();
    $legacyImages = $config['images'] ?? [];
    $ctaUrl = \App\Support\LinkResolver::fromConfig($config, 'cta_');
@endphp

<section class="bg-white py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @forelse($posts as $post)
                @php($src = $post->imageUrl('400'))
                <a href="{{ $post->permalink ?: '#' }}" target="_blank" rel="noopener" class="group relative block aspect-square overflow-hidden rounded-xl bg-primary-light">
                    @if($src)
                        <img src="{{ $src }}" alt="{{ \Illuminate\Support\Str::limit($post->caption ?? '', 100) }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @endif
                    <span class="absolute inset-0 flex items-center justify-center bg-primary/0 text-white opacity-0 transition group-hover:bg-primary/40 group-hover:opacity-100">
                        <x-lucide name="instagram" class="h-7 w-7" />
                    </span>
                </a>
            @empty
                @forelse($legacyImages as $imageId)
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
            @endforelse
        </div>

        @if($ctaUrl && ! empty($config['cta_text']))
            <div class="mt-10 text-center">
                @include('bricks.partials.button', ['btn' => [
                    'text' => $config['cta_text'] ?? null,
                    'style' => $config['cta_style'] ?? 'outline',
                    'color' => $config['cta_color'] ?? null,
                    'icon' => $config['cta_icon'] ?? 'instagram',
                    'link_type' => $config['cta_link_type'] ?? null,
                    'page_id' => $config['cta_page_id'] ?? null,
                    'url' => $config['cta_url'] ?? null,
                ]])
            </div>
        @endif
    </div>
</section>
