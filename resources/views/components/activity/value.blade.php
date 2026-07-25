@props([
    'value' => null,
    'attribute' => null,
    'subject' => null,
    'scope' => [],
    'struck' => false,
])

@php
    use App\Support\ActivityLog\ActivityValue;

    $rows = ActivityValue::rows($value, $attribute, $subject, scope: $scope);
    $isFlat = count($rows) === 1 && $rows[0]['label'] === null;
@endphp

@if ($isFlat)
    <span @class(['break-words', 'line-through decoration-danger-400/60' => $struck])>{{ $rows[0]['value'] }}</span>
@else
    {{-- Structures (page content, billing snapshots, config) get a row per entry
         instead of a JSON blob squeezed into the cell. --}}
    <dl @class(['flex flex-col gap-1', 'line-through decoration-danger-400/60' => $struck])>
        @foreach ($rows as $row)
            <div class="flex flex-wrap gap-x-2">
                <dt class="shrink-0 text-xs font-medium uppercase tracking-wide text-gray-400">{{ $row['label'] }}</dt>
                <dd class="min-w-0 break-words">{{ $row['value'] }}</dd>
            </div>
        @endforeach
    </dl>
@endif
