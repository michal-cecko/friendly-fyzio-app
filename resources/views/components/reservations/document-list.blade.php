@props([
    'documents',
    'removeUrl' => null,
    'removeWire' => null,
])

{{-- Uploaded attachments, shared by the client zone, the signed upload page and
     the admin. `removeUrl` renders a POST form (magic link), `removeWire` a
     Livewire click handler; omitting both makes the list read-only. --}}
@if($documents->isNotEmpty())
    <ul class="mt-4 space-y-2">
        @foreach($documents as $document)
            <li class="flex items-center gap-3 rounded-xl border border-line bg-white px-4 py-3">
                <span class="text-neutral-400">{!! \App\Support\Icon::render('file-text', 'h-5 w-5') !!}</span>

                <span class="min-w-0 flex-1 text-left">
                    <a href="{{ $document->downloadUrl() }}" class="block truncate text-sm font-medium text-neutral-900 underline-offset-2 hover:underline">{{ $document->original_name }}</a>
                    <span class="block text-xs text-neutral-500">
                        {{ $document->sizeForHumans() }} · nahráno {{ $document->created_at->translatedFormat('j. n. Y H:i') }}
                    </span>
                </span>

                @if($removeUrl)
                    <form method="POST" action="{{ $removeUrl }}">
                        @csrf
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="document" value="{{ $document->getKey() }}">
                        <button type="submit" class="shrink-0 rounded-full p-1.5 text-neutral-400 transition hover:bg-red-50 hover:text-red-600" title="Odebrat soubor" aria-label="Odebrat {{ $document->original_name }}">
                            {!! \App\Support\Icon::render('trash-2', 'h-4 w-4') !!}
                        </button>
                    </form>
                @elseif($removeWire)
                    <button
                        type="button"
                        wire:click="{{ $removeWire }}('{{ $document->getKey() }}')"
                        wire:loading.attr="disabled"
                        class="shrink-0 rounded-full p-1.5 text-neutral-400 transition hover:bg-red-50 hover:text-red-600"
                        title="Odebrat soubor"
                        aria-label="Odebrat {{ $document->original_name }}"
                    >{!! \App\Support\Icon::render('trash-2', 'h-4 w-4') !!}</button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
