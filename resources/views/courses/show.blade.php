@extends('layouts.public')

@php
    use App\Enums\OfferState;
    use App\Support\Enrollments\EnrollmentEmailContext;
    use App\Support\Media;

    $image = Media::url($course->featured_image, '1200');
    $showSignupForm = $series !== null && ($presale || in_array($state, [OfferState::Open, OfferState::Full], true));
@endphp

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled — tento kurz zatím není publikovaný{{ $presale ? ' (předprodejní odkaz)' : '' }}.
        </div>
    @endif

    <x-site.breadcrumbs :items="[
        ['label' => 'Pohybové kurzy', 'url' => url('/kurzy')],
        ['label' => $course->name, 'url' => null],
    ]" />

    {{-- Hero --}}
    <section class="bg-surface-alt">
        <div class="ff-container grid grid-cols-1 gap-10 py-12 lg:grid-cols-[1fr_26rem] lg:py-16">
            <div class="min-h-64 overflow-hidden rounded-2xl bg-primary-light lg:min-h-[34rem]">
                @if($image)
                    <img src="{{ $image }}" alt="{{ $course->name }}" class="h-full w-full object-cover">
                @endif
            </div>

            <aside class="flex h-fit flex-col gap-6 rounded-2xl border border-line bg-white p-8">
                <x-site.offer-state-badge :state="$state" />

                <div class="flex flex-col gap-3">
                    <h1 class="font-heading text-[28px] font-bold leading-tight text-neutral-900">{{ $course->name }}</h1>
                    @if($course->description)
                        <p class="text-[15px] leading-relaxed text-neutral-600">{{ str(\App\Support\RichText::plainText($course->description))->limit(160) }}</p>
                    @endif
                </div>

                <hr class="border-line">

                <ul class="flex flex-col gap-4 text-sm text-neutral-900">
                    @if($series)
                        @if($series->totalLessonsCount() > 0)
                            <li class="flex items-center gap-3">
                                <x-lucide name="book-open" class="h-4.5 w-4.5 shrink-0 text-primary" />
                                {{ $series->totalLessonsCount() }} {{ $series->totalLessonsCount() === 1 ? 'lekce' : ($series->totalLessonsCount() <= 4 ? 'lekce' : 'lekcí') }} / série
                            </li>
                        @endif
                        <li class="flex items-center gap-3">
                            <x-lucide name="layers" class="h-4.5 w-4.5 shrink-0 text-primary" />
                            Série: {{ $series->name }}
                        </li>
                        <li class="flex items-center gap-3">
                            <x-lucide name="calendar" class="h-4.5 w-4.5 shrink-0 text-primary" />
                            {{ EnrollmentEmailContext::seriesPeriod($series) }}
                        </li>
                        @if($seriesLessons->isNotEmpty() && $seriesLessons->first()->room)
                            <li class="flex items-center gap-3">
                                <x-lucide name="map-pin" class="h-4.5 w-4.5 shrink-0 text-primary" />
                                {{ EnrollmentEmailContext::place($seriesLessons->first()->room) }}
                            </li>
                        @endif
                        @php
                            $spotsLeft = $series->spotsLeft();
                            $spotsTone = $series->isFull() ? 'text-red-600' : ($spotsLeft <= 3 ? 'text-amber-600' : 'text-emerald-700');
                        @endphp
                        <li class="flex items-center gap-3 font-semibold {{ $spotsTone }}">
                            <x-lucide name="user-check" class="h-4.5 w-4.5 shrink-0" />
                            {{ $series->isFull() ? 'Plně obsazeno' : "Zbývá {$spotsLeft} z {$series->capacity} míst" }}
                        </li>
                        <li class="flex items-center gap-3 font-semibold">
                            <x-lucide name="tag" class="h-4.5 w-4.5 shrink-0 text-primary" />
                            {{ number_format($series->currentPrice(), 0, ',', ' ') }} Kč / série
                        </li>
                    @else
                        <li class="flex items-center gap-3 text-neutral-500">
                            <x-lucide name="calendar-clock" class="h-4.5 w-4.5 shrink-0 text-primary" />
                            Novou sérii připravujeme — nechte nám e-mail níže.
                        </li>
                    @endif
                    @if($course->instructor)
                        <li class="flex items-center gap-3">
                            <x-lucide name="user" class="h-4.5 w-4.5 shrink-0 text-primary" />
                            {{ $course->instructor->name }}
                        </li>
                    @endif
                </ul>

                <hr class="border-line">

                @if($showSignupForm && $state === OfferState::Open)
                    <a href="#prihlaseni" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-8 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                        Přihlásit se na kurz
                        <x-lucide name="arrow-right" class="h-5 w-5" />
                    </a>
                @elseif($showSignupForm && $state === OfferState::Full)
                    <a href="#prihlaseni" class="inline-flex items-center justify-center gap-2.5 rounded-full border-[1.5px] border-primary bg-white px-8 py-[16px] font-heading text-base font-semibold text-primary transition hover:bg-primary-light">
                        <x-lucide name="list" class="h-5 w-5" />
                        Přidat se na čekací listinu
                    </a>
                @else
                    <a href="#prihlaseni" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-surface-muted px-8 py-[18px] font-heading text-base font-semibold text-neutral-500">
                        <x-lucide name="bell" class="h-5 w-5" />
                        Chci vědět o otevření
                    </a>
                @endif
            </aside>
        </div>
    </section>

    {{-- Anchor tabs --}}
    <nav class="border-b border-line bg-white">
        <div class="ff-container flex gap-8 overflow-x-auto text-sm font-medium text-neutral-500">
            <a href="#o-kurzu" class="whitespace-nowrap border-b-2 border-primary py-4 font-semibold text-neutral-900">O kurzu</a>
            @if($seriesLessons->isNotEmpty())
                <a href="#terminy" class="whitespace-nowrap border-b-2 border-transparent py-4 transition hover:text-primary">Termíny lekcí</a>
            @endif
            <a href="#prihlaseni" class="whitespace-nowrap border-b-2 border-transparent py-4 transition hover:text-primary">Přihlášení</a>
            @if($reviews->isNotEmpty())
                <a href="#recenze" class="whitespace-nowrap border-b-2 border-transparent py-4 transition hover:text-primary">Recenze</a>
            @endif
        </div>
    </nav>

    {{-- Content --}}
    <section id="o-kurzu" class="bg-white py-14 lg:py-20">
        <div class="ff-container grid grid-cols-1 gap-12 lg:grid-cols-[1fr_22.5rem]">
            <div class="flex flex-col gap-6">
                <h2 class="font-heading text-2xl font-bold text-neutral-900">O kurzu</h2>
                @if($course->description)
                    <div class="ff-prose text-base leading-[1.7] text-neutral-600">{!! \App\Support\RichText::resolveMentions($course->description) !!}</div>
                @else
                    <p class="text-base text-neutral-500">Podrobný popis kurzu doplníme co nevidět. Máte otázku? Ozvěte se nám.</p>
                @endif

                @if($upcomingLessons->isNotEmpty())
                    <div class="mt-4 flex flex-col gap-4">
                        <h3 class="font-heading text-xl font-semibold text-neutral-900">Jednorázové lekce</h3>
                        <p class="text-sm text-neutral-600">Nechcete se vázat na celou sérii? Vyzkoušejte jednorázovou lekci.</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach($upcomingLessons as $upcomingLesson)
                                <a href="{{ $upcomingLesson->permalink() }}" class="group flex items-center justify-between gap-4 rounded-xl border border-line p-5 transition hover:border-primary">
                                    <span class="flex flex-col gap-1">
                                        <span class="font-heading text-sm font-semibold text-neutral-900">{{ $upcomingLesson->startsAt()->translatedFormat('l j. n. Y') }}</span>
                                        <span class="text-sm text-neutral-600">{{ $upcomingLesson->startsAt()->format('H:i') }} · {{ number_format($upcomingLesson->price, 0, ',', ' ') }} Kč · zbývá {{ $upcomingLesson->spotsLeft() }} míst</span>
                                    </span>
                                    <x-lucide name="chevron-right" class="h-5 w-5 shrink-0 text-primary transition group-hover:translate-x-0.5" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <aside class="flex h-fit flex-col gap-6">
                @if($course->instructor)
                    @php($profile = $course->instructor->staffProfile)
                    <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface-alt p-8 text-center">
                        @php($avatar = $profile?->photo ? Media::url($profile->photo, '200') : null)
                        <span class="h-20 w-20 overflow-hidden rounded-full bg-primary-light">
                            @if($avatar)
                                <img src="{{ $avatar }}" alt="{{ $course->instructor->name }}" class="h-full w-full object-cover">
                            @else
                                <span class="flex h-full w-full items-center justify-center font-heading text-xl font-bold text-primary">{{ str($course->instructor->name)->substr(0, 1) }}</span>
                            @endif
                        </span>
                        <span class="flex flex-col gap-1">
                            <span class="font-heading text-base font-semibold text-neutral-900">{{ $course->instructor->name }}</span>
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
                    <p class="text-sm leading-relaxed text-neutral-700">Kurz je vhodný pro začátečnice i pokročilé. S sebou pohodlné oblečení, podložku a láhev s vodou. V případě zdravotních omezení nás kontaktujte předem.</p>
                </div>
            </aside>
        </div>
    </section>

    {{-- Lesson schedule --}}
    @if($seriesLessons->isNotEmpty())
        <section id="terminy" class="bg-surface-alt py-14 lg:py-20">
            <div class="ff-container flex flex-col gap-8">
                <div class="flex flex-col gap-2">
                    <h2 class="font-heading text-2xl font-bold text-neutral-900">Termíny lekcí</h2>
                    <p class="text-sm text-neutral-600">Rozvrh série {{ $series->name }}.</p>
                </div>
                <ol class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($seriesLessons as $seriesLesson)
                        @php($lessonPast = $seriesLesson->lesson_date->isBefore(today()))
                        <li @class([
                            'flex items-center gap-4 rounded-xl border border-line bg-white p-4',
                            'opacity-50' => $lessonPast,
                        ])>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-light font-heading text-sm font-bold text-primary-dark">{{ $loop->iteration }}</span>
                            <span class="flex flex-col">
                                <span class="font-heading text-sm font-semibold text-neutral-900">{{ $seriesLesson->lesson_date->translatedFormat('l j. n. Y') }}</span>
                                <span class="text-sm text-neutral-600">{{ str($seriesLesson->start_time)->substr(0, 5) }}–{{ str($seriesLesson->end_time)->substr(0, 5) }}{{ $seriesLesson->room ? ' · '.$seriesLesson->room->name : '' }}</span>
                            </span>
                            @if($lessonPast)
                                <span class="ml-auto text-xs font-semibold uppercase tracking-wide text-neutral-400">Proběhlo</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- Sign-up --}}
    <section id="prihlaseni" class="bg-white py-14 lg:py-20">
        <div class="ff-container flex flex-col gap-8">
            <div class="flex flex-col gap-2">
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.12em] text-primary">Přihlášení</p>
                <h2 class="font-heading text-3xl font-bold text-neutral-900">{{ $showSignupForm ? 'Přihlaste se na kurz' : 'Kurz připravujeme' }}</h2>
            </div>

            @if($showSignupForm)
                <livewire:offer-signup-form offer-type="series" :offer-id="$series->getKey()" :presale="$presale" />
            @else
                <livewire:course-interest-form :course="$course" />
            @endif
        </div>
    </section>

    {{-- Reviews --}}
    @if($reviews->isNotEmpty())
        <div id="recenze">
            @include('bricks.reviews', [
                'config' => ['eyebrow' => 'Recenze', 'title' => 'Co říkají účastnice', 'background' => 'alt'],
                'reviews' => $reviews,
            ])
        </div>
    @endif

    {{-- CTA --}}
    <section class="bg-primary-light">
        <div class="ff-container flex flex-col items-center gap-5 py-16 text-center">
            <h2 class="font-heading text-3xl font-bold text-neutral-900">Nevíte si rady s výběrem?</h2>
            <p class="max-w-xl text-base leading-relaxed text-neutral-700">Napište nám nebo zavolejte — rádi vám poradíme, který kurz je pro vás ten pravý.</p>
            <a href="{{ url('/kontakt') }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                Kontaktujte nás
                <x-lucide name="arrow-right" class="h-5 w-5" />
            </a>
        </div>
    </section>
@endsection
