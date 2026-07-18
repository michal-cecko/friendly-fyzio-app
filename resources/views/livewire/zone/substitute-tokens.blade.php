<div class="flex flex-col gap-5">
    <h1 class="font-heading text-2xl font-bold text-neutral-900">Náhradní vstupy</h1>

    @if(session('status'))
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</p>
    @endif

    {{-- Info callout --}}
    <div class="rounded-2xl border border-primary/20 bg-primary-light/40 p-5">
        <p class="flex items-center gap-2 font-heading text-sm font-bold text-neutral-900">
            <x-lucide name="info" class="h-4 w-4 shrink-0 text-primary" />
            Jak fungují náhradní vstupy?
        </p>
        <p class="mt-2 text-sm leading-relaxed text-neutral-600">
            Když se z lekce svého kurzu odhlásíte včas, vystavíme vám náhradní vstup. Ten si můžete uplatnit
            na volné místo v souběžné skupině — stačí vybrat termín níže. Počet náhrad za kurz i lhůta pro
            včasnou omluvu jsou dané podmínkami kurzu, po vypršení platnosti vstup propadá.
        </p>
    </div>

    @error('redeem')
        <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @if($tokens->isEmpty())
        <div class="rounded-2xl border border-line bg-white px-6 py-12 text-center">
            <p class="font-heading text-base font-semibold text-neutral-900">Zatím nemáte žádné náhradní vstupy.</p>
            <p class="mt-2 text-sm text-neutral-500">Vzniknou včasnou omluvou z lekce v <a href="{{ route('zone.courses') }}" class="font-medium text-primary-dark underline">Mých kurzech</a>.</p>
        </div>
    @else
        <div class="rounded-2xl border border-line bg-white p-6">
            <div class="overflow-x-auto">
                <table class="w-full min-w-md text-left text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs uppercase tracking-wide text-neutral-400">
                            <th class="pb-2 pr-4 font-medium">Kurz</th>
                            <th class="pb-2 pr-4 font-medium">Zmeškaná lekce</th>
                            <th class="pb-2 pr-4 font-medium">Platnost</th>
                            <th class="pb-2 pr-4 font-medium">Stav</th>
                            <th class="pb-2 pr-4 font-medium">Náhrada</th>
                            <th class="pb-2 text-right font-medium">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tokens as $token)
                            @php
                                $used = $token->used_at !== null;
                                $expired = ! $used && $token->expires_at !== null && $token->expires_at->isPast();
                                $active = ! $used && ! $expired;
                            @endphp
                            <tr class="border-b border-line/70 last:border-0">
                                <td class="py-3 pr-4 font-medium text-neutral-900">{{ $token->sourceLesson?->series?->course?->name ?? '—' }}</td>
                                <td class="py-3 pr-4 text-neutral-600">
                                    {{ $token->sourceLesson?->lesson_date?->format('j. n. Y') ?? '—' }}
                                </td>
                                <td class="py-3 pr-4 text-neutral-600">{{ $token->expires_at?->format('j. n. Y') ?? '—' }}</td>
                                <td class="py-3 pr-4">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $active,
                                        'bg-neutral-100 text-neutral-600' => $used,
                                        'bg-red-50 text-red-600' => $expired,
                                    ])>
                                        {{ $active ? 'Dostupný' : ($used ? 'Použitý' : 'Propadlý') }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-neutral-600">
                                    @if($used && $token->usedForLesson)
                                        {{ $token->usedForLesson->series?->course?->name }}<br>
                                        <span class="text-xs text-neutral-500">{{ $token->usedForLesson->lesson_date?->format('j. n. Y') }}</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 text-right">
                                    @if($active)
                                        <button
                                            type="button"
                                            wire:click="openRedeem('{{ $token->id }}')"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 font-heading text-xs font-semibold text-white transition hover:bg-primary-dark"
                                        >
                                            <x-lucide name="ticket" class="h-3.5 w-3.5" />
                                            Použít vstup
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
        </div>
    @endif

    {{-- Use-token modal --}}
    @if($redeemingToken)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="closeRedeem"></div>

            <div class="relative flex max-h-[85vh] w-full max-w-lg flex-col rounded-3xl bg-white p-8 shadow-xl">
                <div class="flex justify-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-light text-primary">
                        <x-lucide name="ticket" class="h-7 w-7" />
                    </span>
                </div>

                <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Vyberte náhradní lekci</h2>
                <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
                    Náhradní vstup za {{ $redeemingToken->sourceLesson?->series?->course?->name }}
                    ({{ $redeemingToken->sourceLesson?->lesson_date?->format('j. n. Y') }}). Vyberte si volný termín:
                </p>

                <div class="mt-5 flex-1 overflow-y-auto">
                    @if($options->isEmpty())
                        <p class="rounded-xl bg-surface-muted px-4 py-3 text-center text-sm text-neutral-500">
                            Momentálně nejsou volné náhradní termíny. Zkuste to prosím později — nebo nám napište.
                        </p>
                    @else
                        <div class="flex flex-col gap-2.5">
                            @foreach($options as $lesson)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-heading text-sm font-semibold text-neutral-900">{{ $lesson->series?->course?->name }}</p>
                                        <p class="mt-0.5 text-xs text-neutral-500">
                                            {{ $lesson->lesson_date->format('j. n. Y') }} · {{ \Illuminate\Support\Str::substr($lesson->start_time, 0, 5) }}
                                            @if($lesson->room?->name) · {{ $lesson->room->name }} @endif
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="redeem('{{ $lesson->id }}')"
                                        wire:loading.attr="disabled"
                                        class="shrink-0 rounded-full bg-primary px-4 py-1.5 font-heading text-xs font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60"
                                    >
                                        Vybrat
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <button type="button" wire:click="closeRedeem" class="mt-5 w-full rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                    Zavřít
                </button>
            </div>
        </div>
    @endif
</div>
