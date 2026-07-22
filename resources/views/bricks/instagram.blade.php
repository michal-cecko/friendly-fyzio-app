@php
    $config ??= [];
    // Only posts synced from a connected Instagram account render publicly; a block
    // without them is a placeholder (hidden from visitors, shown to admins only).
    $posts ??= collect();
    $ctaUrl = \App\Support\LinkResolver::fromConfig($config, 'cta_');
    // Set by InstagramBrick::toHtml for admins when only placeholders would render.
    $showPlaceholderWarning ??= false;
    $connectUrl ??= null;
@endphp

<section class="bg-white py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($showPlaceholderWarning)
            <div class="mb-8 flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <x-lucide name="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0" />
                    <p class="text-sm">
                        <strong>Toto je pouze zástupný náhled</strong> — není propojen žádný Instagram účet, takže se zde zobrazují prázdné dlaždice.
                        Návštěvníkům se tento blok vůbec nezobrazuje, vidíte ho jen jako administrátor.
                    </p>
                </div>
                @if($connectUrl)
                    <a href="{{ $connectUrl }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700">
                        <x-lucide name="instagram" class="h-4 w-4" />
                        Propojit Instagram účet
                    </a>
                @endif
            </div>
        @endif

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
                {{-- Placeholder tiles — only ever reached by an admin previewing the warning above. --}}
                @for($i = 0; $i < 4; $i++)
                    <div class="aspect-square rounded-xl bg-primary-light"></div>
                @endfor
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
