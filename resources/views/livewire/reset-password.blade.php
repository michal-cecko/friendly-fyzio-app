@php($inputClass = 'w-full rounded-xl border border-line px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30')

<x-auth.split>
    <h1 class="text-center font-heading text-2xl font-bold text-neutral-900">Nastavení nového hesla</h1>
    <p class="mt-2 text-center text-sm text-neutral-500">Zvolte si nové heslo ke svému účtu.</p>

    <form wire:submit="resetPassword" class="mt-7 grid gap-4">
        <div>
            <label class="mb-1 block text-sm font-medium text-neutral-700">E-mail</label>
            <input type="email" wire:model="email" class="{{ $inputClass }}" placeholder="vas@email.cz" autocomplete="email">
            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-neutral-700">Nové heslo</label>
            <input type="password" wire:model="password" class="{{ $inputClass }}" placeholder="Min. 8 znaků" autocomplete="new-password">
            @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-neutral-700">Potvrzení hesla</label>
            <input type="password" wire:model="password_confirmation" class="{{ $inputClass }}" placeholder="Zopakujte heslo" autocomplete="new-password">
        </div>
        <button type="submit" wire:loading.attr="disabled" class="mt-1 rounded-full bg-primary px-7 py-3.5 font-heading font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            Nastavit heslo
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        <a href="{{ route('public.login') }}" class="font-medium text-primary-dark underline">Zpět na přihlášení</a>
    </p>
</x-auth.split>
