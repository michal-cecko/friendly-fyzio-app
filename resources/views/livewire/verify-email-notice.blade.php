<x-auth.split>
    <div class="flex justify-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-light text-primary">
            <x-lucide name="mail-check" class="h-7 w-7" />
        </span>
    </div>

    <h1 class="mt-6 text-center font-heading text-2xl font-bold text-neutral-900">Ověřte svůj e-mail</h1>
    <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
        Na adresu <strong class="text-neutral-700">{{ $email }}</strong> jsme poslali ověřovací odkaz.
        Kliknutím na něj dokončíte přístup do klientské zóny.
    </p>

    @if($resent)
        <p class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm text-emerald-700">Ověřovací e-mail jsme poslali znovu.</p>
    @endif

    @error('resend')
        <p class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-center text-sm text-amber-700">{{ $message }}</p>
    @enderror

    <button
        type="button"
        wire:click="resend"
        wire:loading.attr="disabled"
        class="mt-7 w-full rounded-full bg-primary px-7 py-3.5 font-heading font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60"
    >
        Odeslat ověření znovu
    </button>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-neutral-500 underline hover:text-neutral-700">Odhlásit se</button>
    </form>
</x-auth.split>
