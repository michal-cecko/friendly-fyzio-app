<div class="flex flex-col gap-5">
    <h1 class="font-heading text-2xl font-bold text-neutral-900">Kredity</h1>

    {{-- Balance banner --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-primary-light px-6 py-6">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-primary-dark/70">Aktuální zůstatek</p>
            <p class="mt-1 font-heading text-4xl font-bold text-primary-dark">{{ number_format($balance, 0, ',', ' ') }} Kč</p>
        </div>
        @if($expiry)
            <div class="text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-primary-dark/70">Platnost do</p>
                <p class="mt-1 font-heading text-base font-semibold text-primary-dark">{{ $expiry->format('j. n. Y') }}</p>
            </div>
        @endif
    </div>

    {{-- History --}}
    <div class="rounded-2xl border border-line bg-white p-6">
        <h2 class="font-heading text-base font-bold text-neutral-900">Historie kreditů</h2>

        @if($transactions->isEmpty())
            <p class="mt-4 text-sm text-neutral-500">Zatím tu nejsou žádné pohyby. Kredit vám můžeme připsat např. z dárkového poukazu.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-md text-left text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs uppercase tracking-wide text-neutral-400">
                            <th class="pb-2 pr-4 font-medium">Datum</th>
                            <th class="pb-2 pr-4 font-medium">Popis</th>
                            <th class="pb-2 pr-4 font-medium">Platnost</th>
                            <th class="pb-2 text-right font-medium">Částka</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            <tr class="border-b border-line/70 last:border-0">
                                <td class="py-3 pr-4 text-neutral-600">{{ $transaction->created_at->format('j. n. Y') }}</td>
                                <td class="py-3 pr-4">
                                    <span class="font-medium text-neutral-900">{{ $transaction->type->getLabel() }}</span>
                                    @if($transaction->description)
                                        <span class="block text-xs text-neutral-500">{{ $transaction->description }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-neutral-500">{{ $transaction->expires_at?->format('j. n. Y') ?? '—' }}</td>
                                <td @class([
                                    'py-3 text-right font-heading font-semibold',
                                    'text-emerald-600' => $transaction->amount > 0,
                                    'text-red-600' => $transaction->amount < 0,
                                ])>
                                    {{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount, 0, ',', ' ') }} Kč
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $transactions->onEachSide(1)->links('livewire.partials.pagination') }}
            </div>
        @endif
    </div>
</div>
