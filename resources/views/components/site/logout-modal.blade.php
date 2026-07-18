{{-- Logout confirm dialog (pencil Modal/Logout). Open it from anywhere with
     $dispatch('open-logout-modal'); rendered once per page. --}}
<div
    x-data="{ open: false }"
    x-on:open-logout-modal.window="open = true"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
>
    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>

    <div class="relative w-full max-w-md rounded-3xl bg-white p-8 shadow-xl" @keydown.escape.window="open = false">
        <div class="flex justify-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-light text-primary">
                <x-lucide name="log-out" class="h-7 w-7" />
            </span>
        </div>

        <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Odhlásit se?</h2>
        <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
            Opravdu se chcete odhlásit z klientské zóny? Pro další přístup se budete muset znovu přihlásit.
        </p>

        <div class="mt-6 flex gap-3">
            <button
                type="button"
                @click="open = false"
                class="flex-1 rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt"
            >
                Zrušit
            </button>
            <form method="POST" action="{{ route('logout') }}" class="flex-1">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-full bg-primary px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark">
                    <x-lucide name="log-out" class="h-4 w-4" />
                    Odhlásit se
                </button>
            </form>
        </div>
    </div>
</div>
