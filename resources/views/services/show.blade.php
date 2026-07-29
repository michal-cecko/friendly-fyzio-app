@extends('layouts.public')

@php
    // Deep-link the wizard with this service preselected (?sluzba=…).
    $bookingUrl = route('reservation.wizard', ['sluzba' => $service->slug]);
    $therapists = $service->therapists->filter(fn ($profile) => $profile->user !== null);
    $siblings = \App\Models\Service::public()
        ->where('category_id', $service->category_id)
        ->whereKeyNot($service->getKey())
        ->orderBy('name')
        ->get();
@endphp

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled — tato služba zatím není publikovaná.
        </div>
    @endif

    {{-- Hero --}}
    <section class="bg-surface-alt">
        <div class="ff-container flex flex-col gap-6 py-16 lg:py-20">
            @if($service->category)
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.14em] text-primary">{{ $service->category->name }}</p>
            @endif

            <h1 class="font-heading text-4xl font-bold leading-[1.15] text-neutral-900 lg:text-5xl">{{ $service->name }}</h1>

            @if(filled($service->description))
                <div class="hero-features max-w-2xl text-base text-neutral-700">
                    {!! \App\Support\RichText::resolveMentions($service->description) !!}
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-base text-neutral-700">
                <span class="inline-flex items-center gap-1.5">
                    <x-lucide name="clock" class="h-5 w-5 text-primary" />
                    {{ $service->duration_minutes }} min
                </span>
                <span class="inline-flex items-center gap-1.5 font-heading text-lg font-semibold text-neutral-900">
                    {{ number_format($service->price, 0, ',', ' ') }} Kč
                </span>
                @if($service->exam_type)
                    <span class="inline-flex items-center rounded-full bg-primary-light px-3 py-1 font-heading text-[13px] font-semibold text-primary">
                        {{ $service->exam_type->getLabel() }}
                    </span>
                @endif
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                    <x-lucide name="calendar" class="h-5 w-5" />
                    Objednat se
                </a>
                @if($service->category)
                    <a href="{{ $service->category->permalink }}" class="inline-flex items-center justify-center gap-2.5 rounded-full border border-line bg-white px-9 py-[18px] font-heading text-base font-semibold text-neutral-900 transition hover:border-primary hover:text-primary">
                        <x-lucide name="arrow-left" class="h-5 w-5" />
                        {{ $service->category->name }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- Kdo vás ošetří --}}
    @if($therapists->isNotEmpty())
        <section class="bg-white py-16 lg:py-24">
            <div class="ff-container">
                @include('bricks.partials.heading', ['config' => [
                    'eyebrow' => 'Náš tým',
                    'title' => 'Kdo vás ošetří',
                ]])

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($therapists as $profile)
                        @php
                            $photo = \App\Support\Media::url($profile->photo, '400');
                            $clickable = $profile->isPublished() && filled($profile->slug);
                            $specs = $profile->specializations->pluck('name')->join(' • ');
                            $cardClass = 'group flex h-full flex-col overflow-hidden rounded-2xl border border-line bg-white text-center transition'.($clickable ? ' hover:border-primary hover:shadow-lg hover:shadow-primary/5' : '');
                        @endphp

                        @if($clickable)
                            <a href="{{ $profile->permalink }}" class="{{ $cardClass }}">
                        @else
                            <div class="{{ $cardClass }}">
                        @endif
                            <div class="aspect-square w-full overflow-hidden bg-primary-light">
                                @if($photo)
                                    <img src="{{ $photo }}" alt="{{ $profile->user->full_name }}" class="h-full w-full object-cover transition duration-500 {{ $clickable ? 'group-hover:scale-105' : '' }}">
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col items-center gap-2 p-6">
                                <h3 class="font-heading text-base font-semibold text-neutral-900">{{ $profile->user->full_name }}</h3>
                                @if($profile->title)
                                    <p class="text-sm text-neutral-600">{{ $profile->title }}</p>
                                @endif
                                @if($specs)
                                    <p class="text-xs font-medium text-primary">{{ $specs }}</p>
                                @endif
                                @if($clickable)
                                    <span class="mt-auto inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-2 font-heading text-[13px] font-semibold text-white transition group-hover:bg-primary-dark">
                                        Shlédnout profil
                                        {!! \App\Support\Icon::render('arrow-right', 'h-4 w-4') !!}
                                    </span>
                                @endif
                            </div>
                        @if($clickable)
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Další služby v kategorii --}}
    @if($siblings->isNotEmpty())
        <section class="bg-surface-alt py-16 lg:py-24">
            <div class="ff-container">
                @include('bricks.partials.heading', ['config' => [
                    'eyebrow' => $service->category?->name,
                    'title' => 'Další služby v kategorii',
                ]])

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($siblings as $sibling)
                        <a href="{{ $sibling->permalink }}" class="group flex h-full flex-col gap-3 rounded-2xl border border-line bg-white p-6 transition hover:border-primary hover:shadow-lg hover:shadow-primary/5">
                            @php($icon = $sibling->icon ?: $sibling->category?->icon)
                            @if($icon)
                                <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-primary-light text-primary">
                                    {!! \App\Support\Icon::render($icon, 'h-6 w-6') !!}
                                </span>
                            @endif
                            <h3 class="font-heading text-lg font-semibold text-neutral-900">{{ $sibling->name }}</h3>
                            @if(filled($sibling->description))
                                <p class="text-sm leading-relaxed text-neutral-600">{{ Str::limit(\App\Support\RichText::plainText($sibling->description), 120) }}</p>
                            @endif
                            <span class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-sm text-neutral-600">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-lucide name="clock" class="h-4 w-4 text-primary" />
                                    {{ $sibling->duration_minutes }} min
                                </span>
                                <span class="font-heading font-semibold text-neutral-900">{{ number_format($sibling->price, 0, ',', ' ') }} Kč</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @include('bricks.cta-banner', ['config' => [
        'title' => 'Objednejte se online',
        'subtitle' => 'Vyberte si termín, který vám vyhovuje. Rezervace zabere jen chvilku.',
        'buttons' => [
            ['text' => 'Rezervovat termín', 'url' => $bookingUrl, 'icon' => 'calendar', 'style' => 'white'],
        ],
    ]])
@endsection
