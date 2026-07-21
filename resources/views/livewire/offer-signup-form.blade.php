@php
    use App\Enums\OfferState;

    $inputClasses = 'w-full rounded-xl border border-line bg-white px-4 py-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $labelClasses = 'mb-1.5 block text-sm font-medium text-neutral-900';
    $typeLabel = $offerType === 'series' ? 'kurz' : 'akci';
@endphp

<div class="flex flex-col gap-8">
    @if($errorMessage)
        <div class="flex items-start gap-3 rounded-xl bg-red-50 p-5 text-sm leading-relaxed text-red-800">
            <x-lucide name="circle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
            <p>{{ $errorMessage }}</p>
        </div>
    @endif

    @if($completed === 'signup')
        {{-- Post-submit confirmation --}}
        <div class="flex flex-col gap-6">
            <div class="flex items-start gap-3 rounded-xl bg-emerald-50 p-5">
                <x-lucide name="circle-check" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="flex flex-col gap-1">
                    <p class="font-heading text-[15px] font-semibold text-neutral-900">Vaši přihlášku jsme přijali</p>
                    <p class="text-sm leading-relaxed text-neutral-600">Na e-mail <strong>{{ $email }}</strong> jsme odeslali platební údaje (QR platba, číslo účtu a variabilní symbol). Místo vám držíme {{ $holdHours }} hodin — přihlášení dokončíte uhrazením platby.</p>
                </div>
            </div>

            <div class="max-w-lg rounded-2xl border border-line bg-surface-alt p-8">
                <h3 class="font-heading text-lg font-bold text-neutral-900">Shrnutí objednávky</h3>
                <dl class="mt-5 flex flex-col gap-3 border-t border-line pt-5">
                    @foreach($summaryRows as $row)
                        <div class="flex items-start justify-between gap-4 text-sm">
                            <dt class="text-neutral-500">{{ $row[0] }}</dt>
                            <dd class="text-right font-medium text-neutral-900">{{ $row[1] }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-3">
                        <dt class="font-heading text-sm font-semibold text-neutral-900">Cena</dt>
                        <dd class="font-heading text-lg font-bold text-primary">{{ number_format($price, 0, ',', ' ') }} Kč</dd>
                    </div>
                </dl>
            </div>
        </div>
    @elseif($isEnrolled)
        {{-- Already enrolled (logged-in client) --}}
        <div class="flex items-start gap-3 rounded-xl bg-emerald-50 p-5">
            <x-lucide name="circle-check" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
            <div class="flex flex-col gap-1">
                <p class="font-heading text-[15px] font-semibold text-neutral-900">Jste přihlášen/a</p>
                <p class="text-sm leading-relaxed text-neutral-600">Vaše přihlášení na {{ $typeLabel }} <strong>{{ $offerTitle }}</strong> evidujeme. Detaily jsme vám poslali e-mailem.</p>
            </div>
        </div>
    @elseif($state === OfferState::Open)
        {{-- Open registration --}}
        <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_26rem] lg:gap-12">
            <div class="flex flex-col gap-6">
                @if($midSeries)
                    <div class="flex items-start gap-3 rounded-xl bg-amber-50 p-5">
                        <x-lucide name="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                        <div class="flex flex-col gap-1">
                            <p class="font-heading text-[15px] font-semibold text-neutral-900">Kurz již probíhá</p>
                            <p class="text-sm leading-relaxed text-neutral-600">Přihlášení je možné i v průběhu série. Cena je přepočítána podle počtu zbývajících lekcí — lektorka vás ráda uvede do probíraného učiva.</p>
                        </div>
                    </div>
                @endif

                <form wire:submit="submit" class="flex flex-col gap-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="signup-name" class="{{ $labelClasses }}">Jméno a příjmení</label>
                            <input id="signup-name" type="text" wire:model="name" placeholder="Zadejte své jméno a příjmení" class="{{ $inputClasses }}" autocomplete="name">
                            @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="signup-email" class="{{ $labelClasses }}">E-mail</label>
                            <input id="signup-email" type="email" wire:model="email" placeholder="vas@email.cz" class="{{ $inputClasses }}" autocomplete="email">
                            @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="sm:max-w-xs">
                        <label for="signup-phone" class="{{ $labelClasses }}">Telefon</label>
                        <input id="signup-phone" type="tel" wire:model="phone" placeholder="+420 xxx xxx xxx" class="{{ $inputClasses }}" autocomplete="tel">
                        @error('phone') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="signup-note" class="{{ $labelClasses }}">Poznámka <span class="font-normal text-neutral-400">(nepovinné)</span></label>
                        <textarea id="signup-note" rows="3" wire:model="note" placeholder="Máte nějaké speciální požadavky?" class="{{ $inputClasses }}"></textarea>
                        @error('note') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="inline-flex cursor-pointer items-start gap-2.5 text-sm text-neutral-700">
                            <input type="checkbox" wire:model="terms" class="mt-0.5 h-4.5 w-4.5 rounded border-line text-primary focus:ring-primary/30">
                            <span>Souhlasím se <a href="/storno-podminky" target="_blank" class="text-primary-dark underline">storno podmínkami</a>.</span>
                        </label>
                        @error('terms') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60" wire:loading.attr="disabled">
                            <x-lucide name="credit-card" class="h-5 w-5" wire:loading.remove />
                            <x-lucide name="loader-circle" class="h-5 w-5 animate-spin" wire:loading />
                            Přihlásit se a zaplatit
                        </button>
                    </div>

                    <p class="text-[13px] leading-relaxed text-neutral-500">Po odeslání přihlášky obdržíte e-mail s platebními údaji. Místo je rezervováno {{ $holdHours }} hodin.</p>
                </form>
            </div>

            <aside class="flex flex-col gap-6">
                <div class="rounded-2xl bg-surface-alt p-8">
                    <h3 class="font-heading text-lg font-bold text-neutral-900">Shrnutí objednávky</h3>
                    <dl class="mt-5 flex flex-col gap-3 border-t border-line pt-5">
                        @foreach($summaryRows as $row)
                            <div class="flex items-start justify-between gap-4 text-sm">
                                <dt class="text-neutral-500">{{ $row[0] }}</dt>
                                <dd class="text-right font-medium text-neutral-900">{{ $row[1] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <div class="mt-5 flex flex-col gap-1.5 border-t border-line pt-5">
                        @if($midSeries && $price !== $fullPrice)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-neutral-500">Plná cena</span>
                                <span class="text-neutral-400 line-through">{{ number_format($fullPrice, 0, ',', ' ') }} Kč</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="font-heading text-sm font-semibold text-neutral-900">{{ $midSeries && $price !== $fullPrice ? 'Cena za zbývající lekce' : 'Cena' }}</span>
                            <span class="font-heading text-xl font-bold text-primary">{{ number_format($price, 0, ',', ' ') }} Kč</span>
                        </div>
                        <p class="text-[13px] text-neutral-500">Platba převodem na účet (QR platba)</p>
                        @if($midSeries && $price !== $fullPrice)
                            <p class="mt-2 border-t border-line pt-3 text-[13px] leading-relaxed text-neutral-500">Nastupujete v průběhu série — cena je poměrně snížena podle počtu zbývajících lekcí.</p>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    @elseif($state === OfferState::Full)
        {{-- Full → waitlist --}}
        <div class="flex flex-col gap-8">
            <div class="flex items-start gap-3 rounded-xl bg-red-50 p-5">
                <x-lucide name="users" class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
                <div class="flex flex-col gap-1">
                    <p class="font-heading text-[15px] font-semibold text-neutral-900">Kapacita je plně obsazena</p>
                    <p class="text-sm leading-relaxed text-neutral-600">Všechna místa jsou obsazena. Přidejte se na čekací listinu — jakmile se uvolní místo, ozveme se vám.</p>
                </div>
            </div>

            @if($completed === 'waitlist')
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-emerald-50 p-5">
                    <div class="flex items-start gap-3">
                        <x-lucide name="circle-check" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                        <div class="flex flex-col gap-1">
                            <p class="font-heading text-[15px] font-semibold text-neutral-900">Jste na čekací listině</p>
                            <p class="text-sm leading-relaxed text-neutral-600">Jakmile se uvolní místo, ozveme se vám e-mailem na <strong>{{ $waitlistEmail }}</strong>. Zařazení je nezávazné — kdykoli se můžete odebrat.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="leaveWaitlist" class="inline-flex items-center gap-1.5 font-heading text-sm font-semibold text-primary transition hover:text-primary-dark">
                        <x-lucide name="x" class="h-4 w-4" />
                        Odebrat se z čekací listiny
                    </button>
                </div>
            @else
                <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_26rem] lg:gap-12">
                    <form wire:submit="joinWaitlist" class="flex flex-col gap-5">
                        <div class="flex flex-col gap-1">
                            <h3 class="font-heading text-2xl font-bold text-neutral-900">Přidat se na čekací listinu</h3>
                            <p class="text-[15px] leading-relaxed text-neutral-600">Vyplňte formulář a my vás budeme kontaktovat, jakmile se uvolní místo.</p>
                        </div>

                        <div>
                            <label for="waitlist-name" class="{{ $labelClasses }}">Jméno a příjmení</label>
                            <input id="waitlist-name" type="text" wire:model="waitlistName" placeholder="Zadejte vaše celé jméno" class="{{ $inputClasses }}" autocomplete="name">
                            @error('waitlistName') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="waitlist-email" class="{{ $labelClasses }}">E-mail</label>
                            <input id="waitlist-email" type="email" wire:model="waitlistEmail" placeholder="Zadejte váš e-mail" class="{{ $inputClasses }}" autocomplete="email">
                            @error('waitlistEmail') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="waitlist-phone" class="{{ $labelClasses }}">Telefon <span class="font-normal text-neutral-400">(nepovinné)</span></label>
                            <input id="waitlist-phone" type="tel" wire:model="waitlistPhone" placeholder="Zadejte vaše telefonní číslo" class="{{ $inputClasses }}" autocomplete="tel">
                            @error('waitlistPhone') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <button type="submit" class="inline-flex items-center justify-center gap-2.5 rounded-full border-[1.5px] border-primary bg-white px-9 py-[16px] font-heading text-base font-semibold text-primary transition hover:bg-primary-light disabled:opacity-60" wire:loading.attr="disabled">
                                <x-lucide name="clock-3" class="h-5 w-5" />
                                Přidat se na čekací listinu
                            </button>
                        </div>
                    </form>

                    <aside class="rounded-2xl border border-line bg-surface-alt p-6">
                        <h4 class="font-heading text-base font-bold text-neutral-900">Informace o čekací listině</h4>
                        <ul class="mt-4 flex flex-col gap-3 text-sm leading-relaxed text-neutral-600">
                            <li class="flex gap-2.5"><x-lucide name="check" class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> Budete zařazeni v pořadí, v jakém jste se přihlásili.</li>
                            <li class="flex gap-2.5"><x-lucide name="check" class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> Jakmile se uvolní místo, ozveme se vám e-mailem s platebními údaji.</li>
                            <li class="flex gap-2.5"><x-lucide name="check" class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> Místo je závazně vaše po uhrazení platby — kdo první zaplatí, ten je přihlášen.</li>
                            <li class="flex gap-2.5"><x-lucide name="check" class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> Zařazení je nezávazné, žádné platby předem, kdykoli můžete zrušit.</li>
                        </ul>
                        <p class="mt-5 flex items-center gap-2.5 rounded-lg bg-primary-light px-4 py-3 text-sm font-semibold text-neutral-900">
                            <x-lucide name="users" class="h-4.5 w-4.5 text-primary-dark" />
                            Aktuálně na čekací listině: {{ $waitlistCount }} {{ $waitlistCount === 1 ? 'zájemce' : ($waitlistCount >= 2 && $waitlistCount <= 4 ? 'zájemci' : 'zájemců') }}
                        </p>
                    </aside>
                </div>
            @endif
        </div>
    @else
        {{-- Preparing / inactive --}}
        <div class="flex items-start gap-3 rounded-xl bg-surface-muted p-5">
            <x-lucide name="info" class="mt-0.5 h-5 w-5 shrink-0 text-neutral-400" />
            <div class="flex flex-col gap-1">
                <p class="font-heading text-[15px] font-semibold text-neutral-900">
                    {{ $state === OfferState::Preparing ? 'Přihlašování zatím není otevřené' : 'Přihlašování není možné' }}
                </p>
                <p class="text-sm leading-relaxed text-neutral-600">
                    {{ $state === OfferState::Preparing
                        ? 'Tento termín teprve připravujeme. Jakmile bude vše připraveno, otevřeme přihlašování.'
                        : 'Tento termín již proběhl nebo bylo přihlašování uzavřeno.' }}
                </p>
            </div>
        </div>
    @endif
</div>
