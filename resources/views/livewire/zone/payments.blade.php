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
                                    @if($payment->invoice)
                                        <a href="{{ route('zone.invoices.download', $payment->invoice) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-dark underline">
                                            <x-lucide name="download" class="h-3.5 w-3.5" />
                                            {{ $payment->invoice->invoice_number }}
                                        </a>
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
</div>
