@php($inputClass = 'w-full rounded-xl border border-line px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30')

<div class="bg-surface-alt">
    <div class="ff-container py-16 lg:py-24">
        <div class="mx-auto max-w-md rounded-3xl border border-line bg-white p-8 lg:p-10">
            <h1 class="text-center font-heading text-2xl font-bold text-neutral-900">Přihlášení</h1>
            <p class="mt-2 text-center text-sm text-neutral-500">Účet je vytvořen automaticky při první rezervaci.</p>

            <form wire:submit="authenticate" class="mt-7 grid gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">E-mail</label>
                    <input type="email" wire:model="email" class="{{ $inputClass }}" autocomplete="email">
                    @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">Heslo</label>
                    <input type="password" wire:model="password" class="{{ $inputClass }}" autocomplete="current-password">
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
                Nemáte účet? <a href="{{ route('reservation.wizard') }}" class="font-medium text-primary-dark underline">Objednejte se online</a> a účet vám vytvoříme.
            </p>
        </div>
    </div>
</div>
