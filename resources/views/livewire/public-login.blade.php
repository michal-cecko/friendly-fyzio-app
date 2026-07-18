@php($inputClass = 'w-full rounded-xl border border-line px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30')

<x-auth.split>
    <h1 class="text-center font-heading text-2xl font-bold text-neutral-900">Přihlaste se do klientské zóny</h1>
    <p class="mt-2 text-center text-sm text-neutral-500">Spravujte svá objednání, rezervace, náhradní vstupy i platby na jednom místě.</p>

    @if(session('status'))
        <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm text-emerald-700">{{ session('status') }}</p>
    @endif

    <form wire:submit="authenticate" class="mt-7 grid gap-4">
        <div>
            <label class="mb-1 block text-sm font-medium text-neutral-700">E-mail</label>
            <input type="email" wire:model="email" class="{{ $inputClass }}" placeholder="vas@email.cz" autocomplete="email">
            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <div class="mb-1 flex items-center justify-between">
                <label class="block text-sm font-medium text-neutral-700">Heslo</label>
                <a href="{{ route('password.request') }}" class="text-xs font-medium text-primary-dark underline">Zapomenuté heslo?</a>
            </div>
            <input type="password" wire:model="password" class="{{ $inputClass }}" placeholder="Vaše heslo" autocomplete="current-password">
            @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-neutral-600">
            <input type="checkbox" wire:model="remember" class="h-4 w-4 rounded border-line text-primary focus:ring-primary/30">
            Zapamatovat si mě
        </label>
        <button type="submit" wire:loading.attr="disabled" class="mt-1 rounded-full bg-primary px-7 py-3.5 font-heading font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            Přihlásit se
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        Nemáte účet? <a href="{{ route('public.register') }}" class="font-medium text-primary-dark underline">Zaregistrujte se</a>
        — nebo se rovnou <a href="{{ route('reservation.wizard') }}" class="font-medium text-primary-dark underline">objednejte online</a> a účet vám vytvoříme.
    </p>
</x-auth.split>
