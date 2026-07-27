@php
    use App\Support\Reservations\ConflictFinder;

    $conflicts = ConflictFinder::forReservation($getRecord());
@endphp

<div class="rounded-xl border border-danger-300 bg-danger-50 p-4 dark:border-danger-400/40 dark:bg-danger-400/10">
    <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger-100 text-danger-600 dark:bg-danger-400/20 dark:text-danger-300">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6" />
        </span>

        <div class="min-w-0 flex-1">
            <h3 class="font-semibold text-danger-800 dark:text-danger-200">Konflikt v rozvrhu</h3>
            <p class="mt-0.5 text-sm text-danger-700/80 dark:text-danger-300/80">
                Tato rezervace se překrývá s něčím jiným. Zkontrolujte termín a jednu ze stran přeplánujte nebo zrušte.
            </p>

            <ul class="mt-3 flex flex-col gap-2">
                @foreach ($conflicts as $conflict)
                    @php
                        $other = $conflict->b;
                        $otherUrl = $other->url();
                    @endphp
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-lg bg-white/70 px-3 py-2 dark:bg-white/5">
                        <span class="inline-flex rounded-md bg-danger-100 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-400/20 dark:text-danger-300">{{ $conflict->title }}</span>
                        <span class="text-sm text-gray-700 dark:text-gray-200">{{ $other->time }} · {{ $other->label }}</span>
                        @if ($otherUrl)
                            <a href="{{ $otherUrl }}" class="ms-auto inline-flex items-center gap-1 text-sm font-semibold text-danger-600 hover:text-danger-700 hover:underline dark:text-danger-400">
                                Zobrazit
                                <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
