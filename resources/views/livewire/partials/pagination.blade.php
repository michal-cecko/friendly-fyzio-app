@php
    $pageName = $paginator->getPageName();
@endphp

@if($paginator->hasPages())
    <nav class="flex items-center justify-center gap-1" aria-label="Stránkování" role="navigation">
        <button
            type="button"
            wire:click="previousPage('{{ $pageName }}')"
            @disabled($paginator->onFirstPage())
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-white text-neutral-500 transition hover:border-primary hover:text-primary disabled:pointer-events-none disabled:opacity-40"
            aria-label="Předchozí stránka"
        >
            <x-lucide name="chevron-left" class="h-4.5 w-4.5" />
        </button>

        @foreach($elements as $element)
            @if(is_string($element))
                <span class="inline-flex h-10 w-10 items-center justify-center text-sm text-neutral-500">…</span>
            @endif

            @if(is_array($element))
                @foreach($element as $page => $url)
                    @if($page == $paginator->currentPage())
                        <span aria-current="page" class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary font-heading text-sm font-semibold text-white">{{ $page }}</span>
                    @else
                        <button
                            type="button"
                            wire:click="setPage({{ $page }}, '{{ $pageName }}')"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-white font-heading text-sm font-medium text-neutral-500 transition hover:border-primary hover:text-primary"
                        >{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        <button
            type="button"
            wire:click="nextPage('{{ $pageName }}')"
            @disabled(! $paginator->hasMorePages())
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-white text-neutral-500 transition hover:border-primary hover:text-primary disabled:pointer-events-none disabled:opacity-40"
            aria-label="Další stránka"
        >
            <x-lucide name="chevron-right" class="h-4.5 w-4.5" />
        </button>
    </nav>
@endif
