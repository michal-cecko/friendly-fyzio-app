@php
    use App\Support\ActivityLog\ActivityPresenter;

    /** @var \Spatie\Activitylog\Models\Activity $record */
    $record = $getRecord();
    $props = $record->properties?->toArray() ?? [];

    $isEmail = $record->event === 'email_sent';
    $bodyHtml = $props['body_html'] ?? null;

    $format = function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Ano' : 'Ne';
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $value));
        }

        return (string) $value;
    };

    // Everything except the raw HTML body renders as a labelled key/value list.
    $rows = collect($props)->except('body_html');
@endphp

<div class="flex flex-col gap-4">
    @if ($rows->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $key => $value)
                        <tr>
                            <td class="w-1/3 py-2 pe-4 align-top font-medium text-gray-700 dark:text-gray-200">{{ ActivityPresenter::attributeLabel($key) }}</td>
                            <td class="py-2 align-top text-gray-800 dark:text-gray-100 break-words">
                                @if (is_bool($value))
                                    <span @class([
                                        'inline-flex rounded-md px-2 py-0.5 text-xs font-semibold',
                                        'bg-success-100 text-success-700 dark:bg-success-400/20 dark:text-success-300' => $value,
                                        'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $value,
                                    ])>{{ $value ? 'Ano' : 'Ne' }}</span>
                                @else
                                    {{ $format($value) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($isEmail && filled($bodyHtml))
        <div>
            <p class="mb-2 text-xs uppercase tracking-wide text-gray-400">Náhled e-mailu</p>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10">
                <iframe
                    sandbox=""
                    srcdoc="{{ $bodyHtml }}"
                    class="h-[32rem] w-full"
                    title="Náhled e-mailu"
                ></iframe>
            </div>
        </div>
    @endif
</div>
