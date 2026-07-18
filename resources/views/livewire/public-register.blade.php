@php($inputClass = 'w-full rounded-xl border border-line px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30')

<x-auth.split>
    <h1 class="text-center font-heading text-2xl font-bold text-neutral-900">Vytvořit účet</h1>
    <p class="mt-2 text-center text-sm text-neutral-500">Zaregistrujte se a spravujte své rezervace, kurzy a platby v klientské zóně.</p>

    <form wire:submit="register" class="mt-7 grid gap-4">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-neutral-700">Jméno</label>
                <input type="text" wire:model="first_name" class="{{ $inputClass }}" placeholder="Vaše jméno" autocomplete="given-name">
                @error('first_name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-neutral-700">Příjmení</label>
                <input type="text" wire:model="last_name" class="{{ $inputClass }}" placeholder="Vaše příjmení" autocomplete="family-name">
                @error('last_name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-neutral-700">E-mail</label>
            <input type="email" wire:model="email" class="{{ $inputClass }}" placeholder="vas@email.cz" autocomplete="email">
            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-neutral-700">Telefon</label>
            <input type="tel" wire:model="phone" class="{{ $inputClass }}" placeholder="+420 777 123 456" autocomplete="tel">
            @error('phone') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-neutral-700">Heslo</label>
            <input type="password" wire:model="password" class="{{ $inputClass }}" placeholder="Min. 8 znaků" autocomplete="new-password">
            @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-neutral-700">Potvrzení hesla</label>
            <input type="password" wire:model="password_confirmation" class="{{ $inputClass }}" placeholder="Zopakujte heslo" autocomplete="new-password">
        </div>
        <label class="flex items-start gap-2 text-sm text-neutral-600">
            <input type="checkbox" wire:model="newsletter" class="mt-0.5 h-4 w-4 rounded border-line text-primary focus:ring-primary/30">
            Chci odebírat novinky o kurzech a akcích
        </label>

        <x-turnstile-widget model="turnstileToken" />
        @error('turnstileToken') <span class="block text-xs text-red-600">{{ $message }}</span> @enderror

        <button type="submit" wire:loading.attr="disabled" class="mt-1 rounded-full bg-primary px-7 py-3.5 font-heading font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            Zaregistrovat se
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        Už máte účet? <a href="{{ route('public.login') }}" class="font-medium text-primary-dark underline">Přihlaste se</a>
    </p>
</x-auth.split>
