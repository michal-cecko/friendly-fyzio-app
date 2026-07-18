@php
    $inputClass = 'w-full rounded-xl border border-line px-4 py-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30';
    $labelClass = 'mb-1 block text-sm font-medium text-neutral-700';
    $saveClass = 'inline-flex items-center gap-2 rounded-full bg-primary px-6 py-2.5 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60';
    $okClass = 'rounded-xl bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700';
@endphp

<div class="flex flex-col gap-5">
    <h1 class="font-heading text-2xl font-bold text-neutral-900">Můj profil</h1>

    {{-- Personal details --}}
    <form wire:submit="saveDetails" class="rounded-2xl border border-line bg-white p-6">
        <h2 class="font-heading text-base font-bold text-neutral-900">Osobní údaje</h2>

        @if(session('details-status'))
            <p class="mt-4 {{ $okClass }}">{{ session('details-status') }}</p>
        @endif

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="{{ $labelClass }}">Jméno a příjmení</label>
                <input type="text" wire:model="name" class="{{ $inputClass }}" autocomplete="name">
                @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">E-mail</label>
                <input type="email" wire:model="email" class="{{ $inputClass }}" autocomplete="email">
                @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                <span class="mt-1 block text-xs text-neutral-400">Po změně e-mailu vás požádáme o jeho ověření.</span>
            </div>
            <div>
                <label class="{{ $labelClass }}">Telefon</label>
                <input type="tel" wire:model="phone" class="{{ $inputClass }}" autocomplete="tel">
                @error('phone') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="{{ $saveClass }} mt-5">Uložit změny</button>
    </form>

    {{-- Password --}}
    <form wire:submit="savePassword" class="rounded-2xl border border-line bg-white p-6">
        <h2 class="font-heading text-base font-bold text-neutral-900">Změna hesla</h2>

        @if(session('password-status'))
            <p class="mt-4 {{ $okClass }}">{{ session('password-status') }}</p>
        @endif

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="{{ $labelClass }}">Současné heslo</label>
                <input type="password" wire:model="current_password" class="{{ $inputClass }}" autocomplete="current-password">
                @error('current_password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Nové heslo</label>
                <input type="password" wire:model="password" class="{{ $inputClass }}" placeholder="Min. 8 znaků" autocomplete="new-password">
                @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Potvrzení hesla</label>
                <input type="password" wire:model="password_confirmation" class="{{ $inputClass }}" autocomplete="new-password">
            </div>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="{{ $saveClass }} mt-5">Změnit heslo</button>
    </form>

    {{-- Company billing --}}
    <form wire:submit="saveBilling" class="rounded-2xl border border-line bg-white p-6">
        <h2 class="font-heading text-base font-bold text-neutral-900">Fakturační údaje firmy</h2>
        <p class="mt-1 text-sm text-neutral-500">Vyplňte, pokud potřebujete fakturu na firmu. Použijeme je u nově vystavených faktur.</p>

        @if(session('billing-status'))
            <p class="mt-4 {{ $okClass }}">{{ session('billing-status') }}</p>
        @endif

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="{{ $labelClass }}">Název firmy</label>
                <input type="text" wire:model="billing_name" class="{{ $inputClass }}" placeholder="Firma s.r.o.">
                @error('billing_name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">IČO</label>
                <input type="text" wire:model="company_ico" class="{{ $inputClass }}" placeholder="12345678">
                @error('company_ico') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">DIČ</label>
                <input type="text" wire:model="company_dic" class="{{ $inputClass }}" placeholder="CZ12345678">
                @error('company_dic') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $labelClass }}">Fakturační adresa</label>
                <input type="text" wire:model="billing_address" class="{{ $inputClass }}" placeholder="Ulice 1, 700 30 Ostrava">
                @error('billing_address') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="{{ $saveClass }} mt-5">Uložit údaje</button>
    </form>
</div>
