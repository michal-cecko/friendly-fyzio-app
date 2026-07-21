@extends('layouts.public')

@php
    $heroImage = \App\Support\Media::url($category->featured_image, '800');
@endphp

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled — tato kategorie zatím není publikovaná.
        </div>
    @endif

    <x-site.breadcrumbs :items="[
        ['label' => $category->name, 'url' => null],
    ]" />

    {{-- Hero --}}
    <section class="bg-primary-light">
        <div class="ff-container flex flex-col items-center gap-12 py-16 lg:flex-row lg:justify-between lg:py-20">
            <div class="flex w-full max-w-[600px] flex-col gap-6">
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.14em] text-primary">Jednorázové akce</p>
                <h1 class="font-heading text-4xl font-bold leading-[1.15] text-neutral-900 lg:text-5xl">{{ $category->name }}</h1>
                @if(! empty($category->description))
                    <p class="text-base leading-relaxed text-neutral-700">{!! \App\Support\RichText::inline($category->description) !!}</p>
                @endif
                <div class="flex flex-wrap gap-3">
                    <a href="#akce-archiv" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                        <x-lucide name="calendar" class="h-5 w-5" />
                        Prohlédnout termíny
                    </a>
                </div>
            </div>

            @if($heroImage)
                <div class="aspect-[56/52] w-full max-w-[560px] shrink-0 overflow-hidden rounded-2xl bg-white lg:h-[460px] lg:w-[560px]">
                    <img src="{{ $heroImage }}" alt="{{ $category->name }}" class="h-full w-full object-cover">
                </div>
            @endif
        </div>
    </section>

    {{-- Archive pre-filtered to this category --}}
    <section class="bg-white py-16 lg:py-24" id="akce-archiv">
        <div class="ff-container">
            @include('bricks.partials.heading', ['config' => [
                'eyebrow' => 'Termíny',
                'title' => 'Aktuální nabídka',
                'subtitle' => 'Vyberte si termín, který vám vyhovuje — kapacity jsou aktuální.',
            ]])

            <livewire:one-off-event-archive :config="['category' => $category->slug]" />
        </div>
    </section>
@endsection
