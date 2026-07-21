@php($gateInput = 'w-full rounded-xl border border-line bg-white px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30')
<div class="rounded-xl border-2 border-amber-300 bg-amber-50 p-5">
    <h3 class="font-heading font-semibold text-amber-900">{{ $gate === 'email_exists' ? 'Tento e-mail už známe' : 'Kontrolní vyšetření je pro stávající pacienty' }}</h3>
    <p class="mt-1 text-sm text-[#666666]">{{ $gate === 'email_exists' ? 'K zadanému e-mailu už existuje účet. Přihlášení není povinné — rezervaci k účtu přiřadíme i bez něj. Po přihlášení ji ale rovnou uvidíte ve svém přehledu.' : 'Přihlaste se prosím, abychom ověřili, že jste naším pacientem.' }}</p>
    <div class="mt-4 grid gap-3">
        <div>
            <input type="email" wire:model="loginEmail" placeholder="E-mail" autocomplete="email" class="{{ $gateInput }}">
            @error('loginEmail') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <input type="password" wire:model="loginPassword" placeholder="Heslo" autocomplete="current-password" wire:keydown.enter="logIn" class="{{ $gateInput }}">
            @error('loginPassword') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" wire:click="logIn" wire:loading.attr="disabled" class="rounded-full bg-primary px-7 py-3 font-heading text-[15px] font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">Přihlásit se</button>
            @if ($gate === 'email_exists')
                <button type="button" wire:click="continueWithoutLogin" class="rounded-full border border-amber-300 bg-white px-6 py-3 font-heading text-[15px] font-semibold text-amber-900 transition hover:brightness-95">Pokračovat bez přihlášení</button>
            @endif
            <a href="{{ route('public.login') }}" class="text-sm text-primary-dark underline">Zapomněli jste heslo?</a>
        </div>
    </div>
</div>
