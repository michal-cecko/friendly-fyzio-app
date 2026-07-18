<div class="mx-auto flex w-full max-w-2xl flex-col items-center gap-6 rounded-2xl border border-line bg-surface-alt p-10 text-center">
    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-light">
        <x-lucide name="calendar-clock" class="h-7 w-7 text-primary" />
    </span>

    <div class="flex flex-col gap-2">
        <h3 class="font-heading text-[22px] font-bold text-neutral-900">Připravujeme pro vás nový kurz</h3>
        <p class="mx-auto max-w-md text-[15px] leading-relaxed text-neutral-600">Nechte nám svůj e-mail a budeme vás informovat, jakmile otevřeme přihlašování na tento kurz.</p>
    </div>

    @if($subscribed)
        <div class="flex items-center gap-2.5 rounded-full bg-emerald-50 px-6 py-3 text-sm font-semibold text-emerald-800">
            <x-lucide name="circle-check" class="h-5 w-5 text-emerald-600" />
            Děkujeme! Ozveme se, jakmile otevřeme přihlašování.
        </div>
    @else
        <form wire:submit="subscribe" class="flex w-full max-w-lg flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <label for="interest-email" class="sr-only">E-mail</label>
                <input
                    id="interest-email"
                    type="email"
                    wire:model="email"
                    placeholder="Zadejte svůj e-mail"
                    class="w-full rounded-full border border-line bg-white px-5 py-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                >
                @error('email') <p class="mt-1.5 text-left text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-primary px-7 py-3.5 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60" wire:loading.attr="disabled">
                <x-lucide name="bell" class="h-4.5 w-4.5" />
                Chci vědět první
            </button>
        </form>
        <p class="text-xs text-neutral-500">Vaše údaje použijeme pouze pro oznámení o otevření kurzu.</p>
    @endif
</div>
