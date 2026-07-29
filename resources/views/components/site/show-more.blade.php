{{-- Expand/collapse control for a list that renders only its first few items.
     The items past the cap carry `data-show-more-item` and start hidden; this
     button unhides them and swaps its own label. Wired by the delegated
     listener in resources/js/app.js, so no framework is needed on the page. --}}
@props(['target', 'more', 'less' => 'Zobrazit méně'])

<div class="flex justify-center py-2">
    <button
        type="button"
        data-show-more="{{ $target }}"
        data-more-label="{{ $more }}"
        data-less-label="{{ $less }}"
        aria-controls="{{ $target }}"
        aria-expanded="false"
        class="inline-flex items-center justify-center gap-2 rounded-full border-[1.5px] border-primary bg-white px-7 py-3.5 font-heading text-sm font-semibold text-primary transition hover:bg-primary hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    >
        <span data-show-more-label>{{ $more }}</span>
        <x-lucide name="chevron-down" class="h-[18px] w-[18px] transition-transform duration-200" data-show-more-icon />
    </button>
</div>
