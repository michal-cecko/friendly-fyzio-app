<div>
    @if($status === 'sent')
        <div class="flex flex-col items-start gap-3 rounded-2xl border border-primary/20 bg-primary-light/60 p-6">
            <div class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-primary text-white">
                {!! \App\Support\Icon::render('check', 'h-5 w-5') !!}
            </div>
            <p class="font-heading text-lg font-semibold text-neutral-900">Děkujeme za zprávu!</p>
            <p class="text-sm leading-relaxed text-neutral-600">Ozveme se vám co nejdříve. Obvykle odpovídáme do jednoho pracovního dne.</p>
            <button type="button" wire:click="$set('status', null)" class="mt-1 text-sm font-semibold text-primary transition hover:text-primary-dark">Napsat další zprávu</button>
        </div>
    @else
        <form wire:submit="submit" class="flex flex-col gap-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div class="flex flex-col gap-2">
                    <label for="contact-name" class="text-sm font-medium text-neutral-700">Jméno</label>
                    <input id="contact-name" type="text" wire:model="name" placeholder="Vaše jméno" class="w-full rounded-xl border border-line bg-white px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @error('name') <p class="text-[13px] font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="contact-email" class="text-sm font-medium text-neutral-700">E-mail</label>
                    <input id="contact-email" type="email" wire:model="email" placeholder="vas@email.cz" class="w-full rounded-xl border border-line bg-white px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @error('email') <p class="text-[13px] font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="contact-phone" class="text-sm font-medium text-neutral-700">Telefon</label>
                    <input id="contact-phone" type="tel" wire:model="phone" placeholder="+420 …" class="w-full rounded-xl border border-line bg-white px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @error('phone') <p class="text-[13px] font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="contact-message" class="text-sm font-medium text-neutral-700">Zpráva</label>
                <textarea id="contact-message" wire:model="message" rows="5" placeholder="Napište nám, s čím vám můžeme pomoci…" class="w-full rounded-xl border border-line bg-white px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"></textarea>
                @error('message') <p class="text-[13px] font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-full bg-primary px-7 py-3.5 font-heading font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                    {!! \App\Support\Icon::render('send', 'h-[18px] w-[18px]') !!}
                    <span>{{ $buttonText }}</span>
                </button>
                @if($status === 'error')
                    <p class="text-sm font-medium text-red-600">Něco se pokazilo, zkuste to prosím znovu.</p>
                @endif
            </div>
        </form>
    @endif
</div>
