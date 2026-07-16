@php
    $searchPageUrl = \App\Filament\Pages\Search::getUrl();
@endphp

{{--
    Deep-links the topbar global search to the standalone search page: Enter in the
    global search field, or this icon button, opens /admin/search with the typed query.

    Also owns the Cmd/Ctrl+K binding: Filament's own x-mousetrap binding unbinds itself
    on the first SPA navigation and never re-registers (the persisted topbar input is not
    re-initialized by Alpine), so a document-level listener is registered here instead.
--}}
<div
    x-data="{
        query: '',

        searchPageUrl() {
            const url = @js($searchPageUrl)

            return this.query.trim().length
                ? `${url}?q=${encodeURIComponent(this.query.trim())}`
                : url
        },

        init() {
            if (! window.__ffGlobalSearchHotkey) {
                window.__ffGlobalSearchHotkey = true

                document.addEventListener('keydown', (event) => {
                    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                        event.preventDefault()

                        document.querySelector('.fi-global-search-field input')?.focus()
                    }
                })
            }

            const input = document.querySelector('.fi-global-search-field input')

            if (! input) {
                return
            }

            this.query = input.value

            input.addEventListener('input', () => (this.query = input.value))

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && this.query.trim().length) {
                    window.location.href = this.searchPageUrl()
                }
            })
        },
    }"
>
    <x-filament::icon-button
        href="{{ $searchPageUrl }}"
        x-bind:href="searchPageUrl()"
        tag="a"
        icon="heroicon-o-document-magnifying-glass"
        color="gray"
        label="Rozšířené vyhledávání (včetně smazaných)"
        tooltip="Rozšířené vyhledávání (včetně smazaných)"
    />
</div>
