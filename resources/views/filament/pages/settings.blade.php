<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                Uložit
            </x-filament::button>
        </div>
    </form>

    {{--
        Global search deep-links to an individual setting via #setting-<key> in the
        URL. Scroll that field into view and briefly highlight it so the target is
        obvious. Runs on full load and after Livewire SPA navigation.
    --}}
    <style>
        [data-setting-anchor] {
            scroll-margin-top: 6rem;
            transition: box-shadow 0.3s ease, background-color 0.3s ease;
            border-radius: 0.5rem;
        }

        [data-setting-anchor].setting-search-flash {
            box-shadow: 0 0 0 2px color-mix(in oklab, var(--primary-500) 60%, transparent);
            background-color: color-mix(in oklab, var(--primary-500) 8%, transparent);
        }
    </style>

    {{--
        @script runs once when Livewire boots this component — on both a full page
        load and a wire:navigate (SPA) arrival. A raw <script> tag would NOT re-run
        on SPA navigation, which is exactly how the panel (->spa()) reaches here.
    --}}
    @script
        <script>
            const hash = window.location.hash;

            if (hash.startsWith('#setting-')) {
                const field = document.getElementById(hash.slice(1));

                if (field && field.hasAttribute('data-setting-anchor')) {
                    requestAnimationFrame(() => {
                        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        field.classList.add('setting-search-flash');
                        setTimeout(() => field.classList.remove('setting-search-flash'), 2200);
                    });
                }
            }
        </script>
    @endscript
</x-filament-panels::page>
