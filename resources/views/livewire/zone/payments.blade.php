@php($selectClass = 'rounded-full border border-line bg-white px-4 py-2 text-sm text-neutral-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20')

<div class="flex flex-col gap-5">
    <h1 class="font-heading text-2xl font-bold text-neutral-900">Platby</h1>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="year" class="{{ $selectClass }}">
            <option value="">Všechny roky</option>
            @foreach($years as $y)
                <option value="{{ $y }}">{{ $y }}</option>
            @endforeach
        </select>

        <select wire:model.live="status" class="{{ $selectClass }}">
            <option value="">Všechny stavy</option>
            @foreach($statuses as $case)
                <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
            @endforeach
        </select>
    </div>

    @if($payments->isEmpty())
        <div class="rounded-2xl border border-line bg-white px-6 py-12 text-center">
            <p class="font-heading text-base font-semibold text-neutral-900">Žádné platby neodpovídají zadaným filtrům.</p>
        </div>
    @else
        <div class="rounded-2xl border border-line bg-white p-6">
            <div class="overflow-x-auto">
                <table class="w-full min-w-md text-left text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs uppercase tracking-wide text-neutral-400">
                            <th class="pb-2 pr-4 font-medium">Datum</th>
                            <th class="pb-2 pr-4 font-medium">Za co</th>
                            <th class="pb-2 pr-4 font-medium">Způsob</th>
                            <th class="pb-2 pr-4 font-medium">Stav</th>
                            <th class="pb-2 pr-4 text-right font-medium">Částka</th>
                            <th class="pb-2 text-right font-medium">Faktura</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            <tr class="border-b border-line/70 last:border-0">
                                <td class="py-3 pr-4 text-neutral-600">{{ ($payment->paid_at ?? $payment->created_at)->format('j. n. Y') }}</td>
                                <td class="py-3 pr-4 font-medium text-neutral-900">{{ $payment->payable_label ?? '—' }}</td>
                                <td class="py-3 pr-4 text-neutral-600">{{ $payment->method?->getLabel() ?? '—' }}</td>
                                <td class="py-3 pr-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $payment->status === \App\Enums\PaymentStatus::Paid,
                                        'bg-amber-50 text-amber-700' => $payment->status === \App\Enums\PaymentStatus::Unpaid,
                                        'bg-red-50 text-red-600' => $payment->status === \App\Enums\PaymentStatus::Overdue,
                                    ])>{{ $payment->status?->getLabel() }}</span>
                                </td>
                                <td class="py-3 pr-4 text-right font-heading font-semibold text-neutral-900">{{ number_format((int) $payment->amount, 0, ',', ' ') }} Kč</td>
                                <td class="py-3 text-right">
                                    @if($payment->status === \App\Enums\PaymentStatus::Paid)
                                        <a href="{{ route('zone.payments.invoice', $payment) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-dark underline">
                                            <x-lucide name="download" class="h-3.5 w-3.5" />
                                            {{ $payment->invoice?->invoice_number ?? 'Stáhnout fakturu' }}
                                        </a>
                                    @elseif($payment->status === \App\Enums\PaymentStatus::Unpaid || $payment->status === \App\Enums\PaymentStatus::Overdue)
                                        <button
                                            type="button"
                                            wire:click="openPayment('{{ $payment->id }}')"
                                            class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-surface-alt"
                                        >
                                            <x-lucide name="credit-card" class="h-3.5 w-3.5" />
                                            Zaplatit
                                        </button>
                                    @else
                                        <span class="text-xs text-neutral-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $payments->onEachSide(1)->links('livewire.partials.pagination') }}
            </div>
        </div>
    @endif

    {{-- "Zaplatit": transfer instructions, the cash → transfer switch, or the credit note --}}
    @if($paying)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="closePayment"></div>

            <div class="relative w-full {{ in_array($paying->method, [\App\Enums\PaymentMethod::Credit, \App\Enums\PaymentMethod::Cash], true) ? 'max-w-lg' : 'max-w-2xl' }} rounded-3xl bg-white p-8 shadow-xl">
                @if($paying->method === \App\Enums\PaymentMethod::Credit)
                    <div class="flex justify-center">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-light text-primary-dark">
                            <x-lucide name="coins" class="h-7 w-7" />
                        </span>
                    </div>

                    <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Platba kreditem</h2>
                    <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
                        Tuto platbu máte hradit kreditem. Kredit odečítá váš terapeut — ozvěte se mu, prosím,
                        a on platbu z vašeho kreditu odečte.
                    </p>

                    <div class="mt-6">
                        <button type="button" wire:click="closePayment" class="w-full rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                            Rozumím
                        </button>
                    </div>
                @elseif($paying->method === \App\Enums\PaymentMethod::Cash)
                    <div class="flex justify-center">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                            <x-lucide name="banknote" class="h-7 w-7" />
                        </span>
                    </div>

                    <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Platba v hotovosti</h2>
                    <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
                        Tuto platbu máte hradit hotově na místě. Přejete si změnit způsob platby na bankovní převod?
                    </p>

                    <div class="mt-6 flex flex-col gap-2.5">
                        <button type="button" wire:click="switchToTransfer" wire:loading.attr="disabled" class="rounded-full bg-primary px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                            Ano, chci platit převodem
                        </button>
                        <button type="button" wire:click="closePayment" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                            Ponechat hotovost
                        </button>
                    </div>
                @else
                    <h2 class="font-heading text-lg font-bold text-neutral-900">Platba převodem</h2>
                    <p class="mt-1 text-sm text-neutral-600">Naskenujte QR kód ve své bankovní aplikaci, nebo zadejte platbu ručně.</p>

                    <div class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-[1fr_auto]">
                        <dl class="grid grid-cols-1 content-start gap-2.5 text-sm">
                            <div class="flex justify-between gap-4 border-b border-line pb-2">
                                <dt class="shrink-0 text-neutral-500">Číslo účtu (IBAN)</dt>
                                <dd class="break-all text-right font-heading font-semibold text-neutral-900">{{ \App\Support\Settings::iban() ?: '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-line pb-2">
                                <dt class="text-neutral-500">Variabilní symbol</dt>
                                <dd class="font-heading font-semibold text-neutral-900">{{ $paying->variable_symbol }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-line pb-2">
                                <dt class="text-neutral-500">Částka</dt>
                                <dd class="font-heading font-semibold text-primary">{{ number_format((int) $paying->amount, 0, ',', ' ') }} Kč</dd>
                            </div>
                            @if($paying->due_at)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-neutral-500">Splatnost</dt>
                                    <dd class="font-heading font-semibold text-neutral-900">{{ $paying->due_at->format('j. n. Y') }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if(\App\Support\Settings::iban() && (int) $paying->amount > 0)
                            <div class="flex items-center justify-center rounded-xl bg-surface-alt p-3">
                                <img src="{{ \App\Support\Payments\QrPlatba::dataUri($paying) }}" alt="QR platba" class="h-40 w-40">
                            </div>
                        @endif
                    </div>

                    <p class="mt-5 text-xs leading-relaxed text-neutral-500">
                        Fakturu si budete moci stáhnout, jakmile platbu zaevidujeme.
                    </p>

                    <div class="mt-5">
                        <button type="button" wire:click="closePayment" class="w-full rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                            Zavřít
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
