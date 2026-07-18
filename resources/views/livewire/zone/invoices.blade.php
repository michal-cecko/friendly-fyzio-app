@php
    use App\Enums\InvoiceStatus;
@endphp

<div class="flex flex-col gap-5">
    <h1 class="font-heading text-2xl font-bold text-neutral-900">Faktury</h1>

    @if($invoices->isEmpty())
        <div class="rounded-2xl border border-line bg-white px-6 py-12 text-center">
            <p class="font-heading text-base font-semibold text-neutral-900">Zatím nemáte žádné faktury.</p>
            <p class="mt-2 text-sm text-neutral-500">Fakturu vystavujeme na vyžádání — napište nám a rádi ji připravíme.</p>
        </div>
    @else
        <div class="rounded-2xl border border-line bg-white p-6">
            <div class="overflow-x-auto">
                <table class="w-full min-w-md text-left text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs uppercase tracking-wide text-neutral-400">
                            <th class="pb-2 pr-4 font-medium">Číslo</th>
                            <th class="pb-2 pr-4 font-medium">Vystaveno</th>
                            <th class="pb-2 pr-4 font-medium">Splatnost</th>
                            <th class="pb-2 pr-4 font-medium">Stav</th>
                            <th class="pb-2 pr-4 text-right font-medium">Částka</th>
                            <th class="pb-2 text-right font-medium">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr class="border-b border-line/70 last:border-0">
                                <td class="py-3 pr-4 font-heading font-semibold text-neutral-900">{{ $invoice->invoice_number }}</td>
                                <td class="py-3 pr-4 text-neutral-600">{{ $invoice->issued_at?->format('j. n. Y') ?? '—' }}</td>
                                <td class="py-3 pr-4 text-neutral-600">{{ $invoice->due_at?->format('j. n. Y') ?? '—' }}</td>
                                <td class="py-3 pr-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $invoice->status === InvoiceStatus::Paid,
                                        'bg-red-50 text-red-600' => $invoice->status === InvoiceStatus::Overdue,
                                        'bg-neutral-100 text-neutral-600' => ! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Overdue], true),
                                    ])>{{ $invoice->status?->getLabel() }}</span>
                                </td>
                                <td class="py-3 pr-4 text-right font-heading font-semibold text-neutral-900">{{ number_format((int) $invoice->amount, 0, ',', ' ') }} Kč</td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('zone.invoices.download', $invoice) }}" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:border-primary hover:text-primary">
                                        <x-lucide name="download" class="h-3.5 w-3.5" />
                                        Stáhnout
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $invoices->onEachSide(1)->links('livewire.partials.pagination') }}
            </div>
        </div>
    @endif
</div>
