@extends('layouts.public')

@php
    $bookingUrl = url('/klientska-zona');
    $heroImage = \App\Support\Media::url($category->hero_image, '800');
@endphp

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled — tato kategorie zatím není publikovaná.
        </div>
    @endif

    {{-- Breadcrumb --}}
    <nav aria-label="Drobečková navigace" class="border-b border-line bg-white">
        <div class="ff-container flex items-center gap-2 py-4 text-sm text-neutral-500">
            <a href="{{ route('home') }}" class="transition hover:text-primary">Domov</a>
            <x-lucide name="chevron-right" class="h-4 w-4 text-neutral-300" />
            <span>Služby</span>
            <x-lucide name="chevron-right" class="h-4 w-4 text-neutral-300" />
            <span class="font-medium text-neutral-900">{{ $category->name }}</span>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="bg-primary-light">
        <div class="ff-container flex flex-col items-center gap-12 py-16 lg:flex-row lg:justify-between lg:py-20">
            <div class="flex w-full max-w-[600px] flex-col gap-6">
                @if($category->type)
                    <p class="font-heading text-sm font-semibold uppercase tracking-[0.14em] text-primary">{{ $category->type->getLabel() }}</p>
                @endif
                <h1 class="font-heading text-4xl font-bold leading-[1.15] text-neutral-900 lg:text-5xl">{{ $category->name }}</h1>
                @if(! empty($category->description))
                    <p class="text-base leading-relaxed text-neutral-700">{!! \App\Support\RichText::inline($category->description) !!}</p>
                @endif
                <div class="flex flex-wrap gap-3">
                    <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                        <x-lucide name="calendar" class="h-5 w-5" />
                        Objednat se
                    </a>
                </div>
            </div>

            <div class="aspect-[56/52] w-full max-w-[560px] shrink-0 overflow-hidden rounded-2xl bg-white lg:h-[460px] lg:w-[560px]">
                @if($heroImage)
                    <img src="{{ $heroImage }}" alt="{{ $category->name }}" class="h-full w-full object-cover">
                @endif
            </div>
        </div>
    </section>

    {{-- Services --}}
    <section class="bg-white py-16 lg:py-24">
        <div class="ff-container">
            @include('bricks.partials.heading', ['config' => [
                'eyebrow' => 'Ceník',
                'title' => 'Naše služby',
                'subtitle' => 'Přehled služeb v této kategorii. Vyberte si a objednejte se online.',
            ]])

            @if($services->isNotEmpty())
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($services as $service)
                        <article class="flex flex-col gap-4 rounded-2xl border border-line bg-white p-6 transition hover:shadow-lg hover:shadow-primary/5">
                            <h3 class="font-heading text-lg font-semibold text-neutral-900">{{ $service->name }}</h3>
                            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-neutral-600">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-lucide name="clock" class="h-4 w-4 text-primary" />
                                    {{ $service->duration_minutes }} min
                                </span>
                                <span class="inline-flex items-center gap-1.5 font-heading font-semibold text-neutral-900">
                                    {{ number_format($service->price, 0, ',', ' ') }} Kč
                                </span>
                            </div>
                            <a href="{{ $bookingUrl }}" class="mt-auto inline-flex items-center gap-1.5 pt-1 font-heading text-sm font-semibold text-primary transition hover:gap-2.5">
                                Objednat se
                                <x-lucide name="arrow-right" class="h-4 w-4" />
                            </a>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="text-center text-neutral-500">V této kategorii zatím nejsou žádné služby.</p>
            @endif
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-primary-light py-16">
        <div class="ff-container flex flex-col items-center gap-6 text-center">
            <h2 class="font-heading text-3xl font-bold text-neutral-900">Chcete se objednat?</h2>
            <p class="max-w-2xl leading-relaxed text-neutral-600">Rezervujte si termín online. Vyberte si službu a čas, který vám vyhovuje.</p>
            <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                <x-lucide name="calendar" class="h-5 w-5" />
                Objednat se
            </a>
        </div>
    </section>
@endsection
