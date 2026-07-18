@extends('layouts.public')

@php
    use App\Enums\OfferState;
    use App\Support\Enrollments\EnrollmentEmailContext;
    use App\Support\Media;

    $image = Media::url($workshop->featured_image, '1200');
    $state = $workshop->offerState();
    $spotsLeft = $workshop->spotsLeft();
@endphp

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled — tento workshop zatím není publikovaný.
        </div>
    @endif

    <x-site.breadcrumbs :items="[
        ['label' => 'Workshopy', 'url' => url('/workshopy')],
        ['label' => $workshop->name, 'url' => null],
    ]" />

    {{-- Hero --}}
    <section class="bg-surface-alt">
        <div class="ff-container grid grid-cols-1 gap-10 py-12 lg:grid-cols-[1fr_27.5rem] lg:py-16">
            <div class="min-h-64 overflow-hidden rounded-2xl bg-primary-light lg:min-h-[30rem]">
                @if($image)
                    <img src="{{ $image }}" alt="{{ $workshop->name }}" class="h-full w-full object-cover">
                @endif
            </div>

            <aside class="flex h-fit flex-col gap-6 rounded-2xl border border-line bg-white p-8">
                <x-site.offer-state-badge :state="$state" />

                <div class="flex flex-col gap-3">
                    <h1 class="font-heading text-[28px] font-bold leading-tight text-neutral-900">{{ $workshop->name }}</h1>
                    @if($workshop->description)
                        <p class="text-[15px] leading-relaxed text-neutral-600">{{ str($workshop->description)->limit(140) }}</p>
                    @endif
                </div>

                <hr class="border-line">

                <ul class="flex flex-col gap-3.5 text-sm font-medium text-neutral-900">
                    <li class="flex items-center gap-3">
                        <x-lucide name="calendar" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        {{ $workshop->startsAt()->translatedFormat('l j. n. Y') }}
                    </li>
                    <li class="flex items-center gap-3">
                        <x-lucide name="clock-3" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        {{ $workshop->startsAt()->format('H:i') }}–{{ $workshop->endsAt()->format('H:i') }}
                    </li>
                    <li class="flex items-center gap-3">
                        <x-lucide name="map-pin" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        {{ EnrollmentEmailContext::place($workshop->room) }}
                    </li>
                    <li class="flex items-center gap-3">
                        <x-lucide name="users" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        Max. {{ $workshop->capacity }} účastníků
                    </li>
                    <li class="flex items-center gap-3 font-semibold">
                        <x-lucide name="tag" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        {{ number_format($workshop->price, 0, ',', ' ') }} Kč
                    </li>
                    <li class="flex items-center gap-3 font-semibold {{ $workshop->isFull() ? 'text-red-600' : ($spotsLeft <= 3 ? 'text-amber-600' : 'text-emerald-700') }}">
                        <x-lucide name="user-check" class="h-4.5 w-4.5 shrink-0" />
                        {{ $workshop->isFull() ? 'Plně obsazeno' : "Zbývá {$spotsLeft} z {$workshop->capacity} míst" }}
                    </li>
                    @if($workshop->instructor)
                        <li class="flex items-center gap-3">
                            <x-lucide name="user" class="h-4.5 w-4.5 shrink-0 text-primary" />
                            {{ $workshop->instructor->name }}
                        </li>
                    @endif
                </ul>

                <hr class="border-line">

                @if($state === OfferState::Open)
                    <a href="#prihlaseni" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-8 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                        Přihlásit se na workshop
                        <x-lucide name="arrow-right" class="h-5 w-5" />
                    </a>
                @elseif($state === OfferState::Full)
                    <a href="#prihlaseni" class="inline-flex items-center justify-center gap-2.5 rounded-full border-[1.5px] border-primary bg-white px-8 py-[16px] font-heading text-base font-semibold text-primary transition hover:bg-primary-light">
                        <x-lucide name="list" class="h-5 w-5" />
                        Přidat se na čekací listinu
                    </a>
                @else
                    <p class="rounded-xl bg-surface-muted px-5 py-4 text-center text-sm font-medium text-neutral-500">
                        {{ $state === OfferState::Preparing ? 'Přihlašování zatím není otevřené.' : 'Tento workshop již proběhl.' }}
                    </p>
                @endif
            </aside>
        </div>
    </section>

    {{-- Anchor tabs --}}
    <nav class="border-b border-line bg-white">
        <div class="ff-container flex gap-8 text-sm font-medium text-neutral-500">
            <a href="#o-workshopu" class="whitespace-nowrap border-b-2 border-primary py-4 font-semibold text-neutral-900">O workshopu</a>
            <a href="#prihlaseni" class="whitespace-nowrap border-b-2 border-transparent py-4 transition hover:text-primary">Přihlášení</a>
            @if($reviews->isNotEmpty())
                <a href="#recenze" class="whitespace-nowrap border-b-2 border-transparent py-4 transition hover:text-primary">Recenze</a>
            @endif
        </div>
    </nav>

    {{-- Content --}}
    <section id="o-workshopu" class="bg-white py-14 lg:py-20">
        <div class="ff-container grid grid-cols-1 gap-12 lg:grid-cols-[1fr_22.5rem]">
            <div class="flex flex-col gap-6">
                <h2 class="font-heading text-2xl font-bold text-neutral-900">O workshopu</h2>
                @if($workshop->description)
                    <div class="whitespace-pre-line text-base leading-[1.7] text-neutral-600">{{ $workshop->description }}</div>
                @else
                    <p class="text-base text-neutral-500">Podrobný popis workshopu doplníme co nevidět. Máte otázku? Ozvěte se nám.</p>
                @endif
            </div>

            <aside class="flex h-fit flex-col gap-6">
                @if($workshop->instructor)
                    @php($profile = $workshop->instructor->therapistProfile)
                    <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface-alt p-8 text-center">
                        @php($avatar = $profile?->photo ? Media::url($profile->photo, '200') : null)
                        <span class="h-20 w-20 overflow-hidden rounded-full bg-primary-light">
                            @if($avatar)
                                <img src="{{ $avatar }}" alt="{{ $workshop->instructor->name }}" class="h-full w-full object-cover">
                            @else
                                <span class="flex h-full w-full items-center justify-center font-heading text-xl font-bold text-primary">{{ str($workshop->instructor->name)->substr(0, 1) }}</span>
                            @endif
                        </span>
                        <span class="flex flex-col gap-1">
                            <span class="font-heading text-base font-semibold text-neutral-900">{{ $workshop->instructor->name }}</span>
                            <span class="text-sm text-neutral-600">{{ $profile?->title ?? 'Lektorka' }}</span>
                        </span>
                        @if($profile !== null && $profile->isPublished() && filled($profile->slug))
                            <a href="{{ $profile->permalink }}" class="inline-flex items-center gap-1.5 rounded-full border-[1.5px] border-primary bg-white px-5 py-2.5 font-heading text-sm font-semibold text-primary transition hover:bg-primary-light">
                                Více o lektorce
                                <x-lucide name="arrow-right" class="h-4 w-4" />
                            </a>
                        @endif
                    </div>
                @endif

                <div class="flex items-start gap-3 rounded-2xl bg-primary-light/60 p-6">
                    <x-lucide name="info" class="mt-0.5 h-5 w-5 shrink-0 text-primary-dark" />
                    <p class="text-sm leading-relaxed text-neutral-700">S sebou pohodlné oblečení a láhev s vodou. Veškeré pomůcky a materiály jsou zajištěny. V případě dotazů nás kontaktujte předem.</p>
                </div>
            </aside>
        </div>
    </section>

    {{-- Sign-up --}}
    <section id="prihlaseni" class="bg-surface-alt py-14 lg:py-20">
        <div class="ff-container flex flex-col gap-8">
            <div class="flex flex-col gap-2">
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.12em] text-primary">Přihlášení</p>
                <h2 class="font-heading text-3xl font-bold text-neutral-900">Přihlášení na workshop</h2>
            </div>

            <livewire:offer-signup-form offer-type="workshop" :offer-id="$workshop->getKey()" />
        </div>
    </section>

    {{-- Reviews --}}
    @if($reviews->isNotEmpty())
        <div id="recenze">
            @include('bricks.reviews', [
                'config' => ['eyebrow' => 'Recenze', 'title' => 'Co říkají účastníci', 'background' => 'white'],
                'reviews' => $reviews,
            ])
        </div>
    @endif

    {{-- CTA --}}
    <section class="bg-primary-light">
        <div class="ff-container flex flex-col items-center gap-5 py-16 text-center">
            <h2 class="font-heading text-3xl font-bold text-neutral-900">Nenašli jste svůj termín?</h2>
            <p class="max-w-xl text-base leading-relaxed text-neutral-700">Podívejte se na všechny vypsané workshopy, nebo nám napište — rádi vás upozorníme na další termíny.</p>
            <a href="{{ url('/workshopy') }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                Všechny workshopy
                <x-lucide name="arrow-right" class="h-5 w-5" />
            </a>
        </div>
    </section>
@endsection
