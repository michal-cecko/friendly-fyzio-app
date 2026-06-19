@extends('layouts.public')

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled konceptu — tato stránka zatím není publikovaná.
        </div>
    @endif

    {!! $renderedContent !!}
@endsection
