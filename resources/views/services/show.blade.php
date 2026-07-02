@extends('layouts.public')

@php
    // Deep-link the wizard with this service preselected (?sluzba=…).
    $bookingUrl = route('reservation.wizard', ['sluzba' => $service->slug]);
@endphp

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled — tato služba zatím není publikovaná.
        </div>
    @endif

    {{-- Breadcrumb --}}
    <nav aria-label="Drobečková navigace" class="border-b border-line bg-white">
        <div class="ff-container flex flex-wrap items-center gap-2 py-4 text-sm text-neutral-500">
            <a href="{{ route('home') }}" class="transition hover:text-primary">Domov</a>
            <x-lucide name="chevron-right" class="h-4 w-4 text-neutral-300" />
            <a href="{{ $category->permalink }}" class="transition hover:text-primary">{{ $category->name }}</a>
            <x-lucide name="chevron-right" class="h-4 w-4 text-neutral-300" />
            <span class="font-medium text-neutral-900">{{ $service->name }}</span>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="bg-primary-light">
        <div class="ff-container flex flex-col gap-6 py-16 lg:py-20">
            @if($service->type)
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.14em] text-primary">{{ $service->type->getLabel() }}</p>
            @endif
            <h1 class="font-heading text-4xl font-bold leading-[1.15] text-neutral-900 lg:text-5xl">{{ $service->name }}</h1>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-base text-neutral-700">
                <span class="inline-flex items-center gap-1.5">
                    <x-lucide name="clock" class="h-5 w-5 text-primary" />
                    {{ $service->duration_minutes }} min
                </span>
                <span class="inline-flex items-center gap-1.5 font-heading text-lg font-semibold text-neutral-900">
                    {{ number_format($service->price, 0, ',', ' ') }} Kč
                </span>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                    <x-lucide name="calendar" class="h-5 w-5" />
                    Objednat se
                </a>
            </div>
        </div>
    </section>
@endsection
