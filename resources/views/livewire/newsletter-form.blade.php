<div class="{{ $compact ? 'flex flex-col gap-2' : 'flex w-full flex-col items-center gap-3' }}">
    @if($compact)
        @if($status === 'subscribed')
            <p class="rounded-full bg-primary/15 px-5 py-2.5 text-sm font-medium text-primary">Děkujeme! Přihlášení k odběru proběhlo úspěšně.</p>
        @elseif($status === 'already')
            <p class="rounded-full bg-white/10 px-5 py-2.5 text-sm font-medium text-neutral-200">Tento e-mail už je k odběru přihlášený.</p>
        @else
            <form wire:submit="subscribe" class="flex gap-2.5">
                <input type="email" wire:model="email" required placeholder="{{ $placeholder }}" class="w-48 rounded-full border border-white/20 bg-neutral-900 px-4 py-2.5 text-sm text-white placeholder:text-neutral-500 focus:border-primary focus:outline-none sm:w-56">
                <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-primary px-5 py-2.5 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">{{ $buttonText }}</button>
            </form>
            @error('email')
                <p class="text-[13px] font-medium text-red-400">{{ $message }}</p>
            @enderror
            @if($status === 'error')
                <p class="text-[13px] font-medium text-red-400">Něco se pokazilo, zkuste to prosím znovu.</p>
            @endif
        @endif
    @else
        @if($status === 'subscribed')
            <p class="rounded-full bg-white px-6 py-3 font-medium text-primary-dark">Děkujeme! Přihlášení k odběru proběhlo úspěšně.</p>
        @elseif($status === 'already')
            <p class="rounded-full bg-white px-6 py-3 font-medium text-neutral-700">Tento e-mail už je k odběru přihlášený.</p>
        @else
            <form wire:submit="subscribe" class="flex w-full max-w-md flex-col gap-3 sm:flex-row sm:justify-center">
                <input type="email" wire:model="email" required placeholder="{{ $placeholder }}" class="w-full rounded-full border border-line bg-white px-5 py-3.5 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 sm:flex-1">
                <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-primary px-7 py-3.5 font-heading font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">{{ $buttonText }}</button>
            </form>
            @error('email')
                <p class="text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror
            @if($status === 'error')
                <p class="text-sm font-medium text-red-600">Něco se pokazilo, zkuste to prosím znovu.</p>
            @endif
        @endif
    @endif
</div>
