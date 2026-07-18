@extends('layouts.public')

@php
    use App\Support\Enrollments\EnrollmentEmailContext;
    use App\Support\Media;

    $image = Media::url($course->featured_image, '1200');
    $state = $lesson->offerState();
    $spotsLeft = $lesson->spotsLeft();
@endphp

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled — tato lekce zatím není publikovaná.
        </div>
    @endif

    <x-site.breadcrumbs :items="[
        ['label' => 'Pohybové kurzy', 'url' => url('/kurzy?typ=lekce')],
        ['label' => $course->name, 'url' => $course->permalink()],
        ['label' => $lesson->startsAt()->format('j. n. Y'), 'url' => null],
    ]" />

    {{-- Hero --}}
    <section class="bg-surface-alt">
        <div class="ff-container grid grid-cols-1 gap-10 py-12 lg:grid-cols-[1fr_26rem] lg:py-16">
            <div class="min-h-64 overflow-hidden rounded-2xl bg-primary-light lg:min-h-[30rem]">
                @if($image)
                    <img src="{{ $image }}" alt="{{ $course->name }}" class="h-full w-full object-cover">
                @endif
            </div>

            <aside class="flex h-fit flex-col gap-6 rounded-2xl border border-line bg-white p-8">
                <x-site.offer-state-badge :state="$state" />

                <div class="flex flex-col gap-3">
                    <h1 class="font-heading text-[28px] font-bold leading-tight text-neutral-900">{{ $course->name }}</h1>
                    @if($course->description)
                        <p class="text-[15px] leading-relaxed text-neutral-600">{{ str($course->description)->limit(140) }}</p>
                    @endif
                </div>

                <hr class="border-line">

                <ul class="flex flex-col gap-4 text-sm text-neutral-900">
                    <li class="flex items-center gap-3">
                        <x-lucide name="calendar" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        {{ $lesson->startsAt()->translatedFormat('l j. n. Y') }}, {{ $lesson->startsAt()->format('H:i') }}–{{ $lesson->endsAt()->format('H:i') }}
                    </li>
                    <li class="flex items-center gap-3">
                        <x-lucide name="map-pin" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        {{ EnrollmentEmailContext::place($lesson->room) }}
                    </li>
                    <li class="flex items-center gap-3">
                        <x-lucide name="users" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        Max. {{ $lesson->capacity }} účastníků
                    </li>
                    <li class="flex items-center gap-3 font-semibold">
                        <x-lucide name="tag" class="h-4.5 w-4.5 shrink-0 text-primary" />
                        {{ number_format($lesson->price, 0, ',', ' ') }} Kč
                    </li>
                    <li class="flex items-center gap-3 font-semibold {{ $lesson->isFull() ? 'text-red-600' : ($spotsLeft <= 3 ? 'text-amber-600' : 'text-emerald-700') }}">
                        <x-lucide name="user-check" class="h-4.5 w-4.5 shrink-0" />
                        {{ $lesson->isFull() ? 'Plně obsazeno' : "Zbývá {$spotsLeft} z {$lesson->capacity} míst" }}
                    </li>
                    @if($lesson->instructor)
                        <li class="flex items-center gap-3">
                            <x-lucide name="user" class="h-4.5 w-4.5 shrink-0 text-primary" />
                            {{ $lesson->instructor->name }}
                        </li>
                    @endif
                </ul>

                <hr class="border-line">

                <a href="#prihlaseni" @class([
                    'inline-flex items-center justify-center gap-2.5 rounded-full px-8 py-[18px] font-heading text-base font-semibold transition',
                    'bg-primary text-white hover:bg-primary-dark' => $state === \App\Enums\OfferState::Open,
                    'border-[1.5px] border-primary bg-white !py-[16px] text-primary hover:bg-primary-light' => $state === \App\Enums\OfferState::Full,
                    'bg-surface-muted text-neutral-500' => ! in_array($state, [\App\Enums\OfferState::Open, \App\Enums\OfferState::Full], true),
                ])>
                    {{ $state === \App\Enums\OfferState::Full ? 'Přidat se na čekací listinu' : 'Rezervovat místo' }}
                    <x-lucide name="arrow-right" class="h-5 w-5" />
                </a>
            </aside>
        </div>
    </section>

    {{-- Anchor tabs --}}
    <nav class="border-b border-line bg-white">
        <div class="ff-container flex gap-8 text-sm font-medium text-neutral-500">
            <a href="#o-lekci" class="whitespace-nowrap border-b-2 border-primary py-4 font-semibold text-neutral-900">O lekci</a>
            <a href="#prihlaseni" class="whitespace-nowrap border-b-2 border-transparent py-4 transition hover:text-primary">Přihlášení</a>
        </div>
    </nav>

    {{-- Content --}}
    <section id="o-lekci" class="bg-white py-14 lg:py-20">
        <div class="ff-container grid grid-cols-1 gap-12 lg:grid-cols-[1fr_22.5rem]">
            <div class="flex flex-col gap-6">
                <h2 class="font-heading text-2xl font-bold text-neutral-900">O lekci</h2>
                @if($course->description)
                    <div class="whitespace-pre-line text-base leading-[1.7] text-neutral-600">{{ $course->description }}</div>
                @else
                    <p class="text-base text-neutral-500">Podrobný popis lekce doplníme co nevidět. Máte otázku? Ozvěte se nám.</p>
                @endif

                @if($otherLessons->isNotEmpty())
                    <div class="mt-4 flex flex-col gap-4">
                        <h3 class="font-heading text-xl font-semibold text-neutral-900">Další termíny</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach($otherLessons as $otherLesson)
                                <a href="{{ $otherLesson->permalink() }}" class="group flex items-center justify-between gap-4 rounded-xl border border-line p-5 transition hover:border-primary">
                                    <span class="flex flex-col gap-1">
                                        <span class="font-heading text-sm font-semibold text-neutral-900">{{ $otherLesson->startsAt()->translatedFormat('l j. n. Y') }}</span>
                                        <span class="text-sm text-neutral-600">{{ $otherLesson->startsAt()->format('H:i') }} · zbývá {{ $otherLesson->spotsLeft() }} míst</span>
                                    </span>
                                    <x-lucide name="chevron-right" class="h-5 w-5 shrink-0 text-primary transition group-hover:translate-x-0.5" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <aside class="flex h-fit flex-col gap-6">
                @if($lesson->instructor)
                    @php($profile = $lesson->instructor->therapistProfile)
                    <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface-alt p-8 text-center">
                        @php($avatar = $profile?->photo ? Media::url($profile->photo, '200') : null)
                        <span class="h-20 w-20 overflow-hidden rounded-full bg-primary-light">
                            @if($avatar)
                                <img src="{{ $avatar }}" alt="{{ $lesson->instructor->name }}" class="h-full w-full object-cover">
                            @else
                                <span class="flex h-full w-full items-center justify-center font-heading text-xl font-bold text-primary">{{ str($lesson->instructor->name)->substr(0, 1) }}</span>
                            @endif
                        </span>
                        <span class="flex flex-col gap-1">
                            <span class="font-heading text-base font-semibold text-neutral-900">{{ $lesson->instructor->name }}</span>
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
                    <p class="text-sm leading-relaxed text-neutral-700">S sebou pohodlné oblečení, podložku a láhev s vodou. V případě zdravotních omezení nás kontaktujte předem.</p>
                </div>
            </aside>
        </div>
    </section>

    {{-- Sign-up --}}
    <section id="prihlaseni" class="bg-surface-alt py-14 lg:py-20">
        <div class="ff-container flex flex-col gap-8">
            <div class="flex flex-col gap-2">
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.12em] text-primary">Přihlášení</p>
                <h2 class="font-heading text-3xl font-bold text-neutral-900">Rezervujte si místo</h2>
            </div>

            <livewire:offer-signup-form offer-type="lesson" :offer-id="$lesson->getKey()" :presale="$presale" />
        </div>
    </section>

    {{-- Reviews --}}
    @if($reviews->isNotEmpty())
        <div id="recenze">
            @include('bricks.reviews', [
                'config' => ['eyebrow' => 'Recenze', 'title' => 'Co říkají účastnice', 'background' => 'white'],
                'reviews' => $reviews,
            ])
        </div>
    @endif

    {{-- CTA --}}
    <section class="bg-primary-light">
        <div class="ff-container flex flex-col items-center gap-5 py-16 text-center">
            <h2 class="font-heading text-3xl font-bold text-neutral-900">Chcete pravidelný pohyb?</h2>
            <p class="max-w-xl text-base leading-relaxed text-neutral-700">Prohlédněte si celé série kurzů — pravidelné lekce pod vedením našich fyzioterapeutek.</p>
            <a href="{{ url('/kurzy') }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                Prohlédnout kurzy
                <x-lucide name="arrow-right" class="h-5 w-5" />
            </a>
        </div>
    </section>
@endsection
