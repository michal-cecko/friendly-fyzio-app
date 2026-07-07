<div class="mx-auto max-w-xl">
    @if($state === 'invalid')
        <div class="rounded-2xl border border-line bg-white p-8 text-center">
            <p class="font-heading text-lg font-semibold text-neutral-900">Neplatný odkaz</p>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">Tento odkaz na recenzi je neplatný nebo už není aktivní.</p>
        </div>
    @elseif($state === 'already')
        <div class="rounded-2xl border border-primary/20 bg-primary-light/60 p-8 text-center">
            <p class="font-heading text-lg font-semibold text-neutral-900">Recenzi už máme</p>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">Tuto recenzi jste už vyplnili. Moc děkujeme za váš čas!</p>
        </div>
    @elseif($state === 'done')
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-primary/20 bg-primary-light/60 p-8 text-center">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-primary text-white">
                {!! \App\Support\Icon::render('check', 'h-6 w-6') !!}
            </div>
            <p class="font-heading text-lg font-semibold text-neutral-900">Děkujeme za recenzi!</p>
            <p class="text-sm leading-relaxed text-neutral-600">Vaše zpětná vazba nám moc pomáhá. Po schválení se objeví na webu.</p>
        </div>
    @else
        <form wire:submit="submit" class="flex flex-col gap-6 rounded-2xl border border-line bg-white p-8">
            <div class="text-center">
                <p class="text-sm text-neutral-600">Jak jste byli spokojeni s</p>
                <p class="font-heading text-xl font-semibold text-neutral-900">{{ $targetLabel }}?</p>
            </div>

            <div class="flex flex-col items-center gap-2">
                <div x-data="{ hover: 0 }" class="flex gap-1">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button"
                            wire:key="star-{{ $i }}"
                            @click="$wire.rating = {{ $i }}"
                            @mouseenter="hover = {{ $i }}"
                            @mouseleave="hover = 0"
                            :class="(hover || $wire.rating) >= {{ $i }} ? 'text-primary' : 'text-neutral-300'"
                            class="transition hover:scale-110"
                            aria-label="{{ $i }} z 5 hvězdiček">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="h-9 w-9" aria-hidden="true"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        </button>
                    @endfor
                </div>
                @error('rating') <p class="text-[13px] font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="review-content" class="text-sm font-medium text-neutral-700">Vaše recenze <span class="text-neutral-400">(nepovinné)</span></label>
                <textarea id="review-content" wire:model="content" rows="5" placeholder="Co vám pomohlo? Jaká byla vaše zkušenost?" class="w-full rounded-xl border border-line bg-white px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"></textarea>
                @error('content') <p class="text-[13px] font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-7 py-3.5 font-heading font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <span>Odeslat recenzi</span>
            </button>
        </form>
    @endif
</div>
