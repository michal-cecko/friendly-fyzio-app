@props(['title' => 'Můj účet'])

@php
    $items = [
        ['route' => 'zone.dashboard', 'pattern' => 'zone.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Přehled'],
        ['route' => 'zone.reservations', 'pattern' => 'zone.reservations*', 'icon' => 'calendar', 'label' => 'Moje rezervace'],
        ['route' => 'zone.courses', 'pattern' => 'zone.courses', 'icon' => 'book-open', 'label' => 'Moje kurzy'],
        ['route' => 'zone.tokens', 'pattern' => 'zone.tokens', 'icon' => 'ticket', 'label' => 'Náhradní vstupy'],
        ['route' => 'zone.credits', 'pattern' => 'zone.credits', 'icon' => 'coins', 'label' => 'Kredity'],
        ['route' => 'zone.payments', 'pattern' => 'zone.payments*', 'icon' => 'wallet', 'label' => 'Platby'],
        ['route' => 'zone.profile', 'pattern' => 'zone.profile', 'icon' => 'user', 'label' => 'Můj profil'],
    ];
@endphp

<section class="bg-surface-alt py-10 lg:py-14">
    <div class="ff-container">
        <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-[16rem_1fr]">
            <aside class="rounded-2xl border border-line bg-white p-4 lg:sticky lg:top-6">
                <p class="px-3 pb-2 pt-1 font-heading text-sm font-bold text-neutral-900">Můj účet</p>

                <nav class="flex flex-col gap-1">
                    @foreach($items as $item)
                        @php($active = request()->routeIs($item['pattern']))
                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition',
                                'bg-primary-light font-semibold text-primary-dark' => $active,
                                'font-medium text-neutral-600 hover:bg-surface-alt hover:text-neutral-900' => ! $active,
                            ])
                        >
                            <x-lucide :name="$item['icon']" @class(['h-4.5 w-4.5 shrink-0', 'text-primary' => $active, 'text-neutral-400' => ! $active]) />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="my-3 h-px bg-line"></div>

                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-logout-modal')"
                    class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-primary-dark transition hover:bg-red-50"
                >
                    <x-lucide name="log-out" class="h-4.5 w-4.5 shrink-0" />
                    Odhlásit se
                </button>
            </aside>

            <div class="min-w-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</section>

<x-site.logout-modal />
