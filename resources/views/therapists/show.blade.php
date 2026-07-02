@extends('layouts.public')

@php
    $name = $therapist->user?->name ?? 'Terapeut';
    $phone = $therapist->user?->phone ?: \App\Support\Settings::get('web.contact_phone');
    $email = $therapist->user?->email ?: \App\Support\Settings::get('web.contact_email');
    $address = \App\Support\Settings::get('web.address');
    $photo = \App\Support\Media::url($therapist->photo, '800');
    $bookingUrl = route('reservation.wizard', ['terapeut' => $therapist->id]);
    $education = $therapist->education ?? [];
    $certifications = $therapist->certifications ?? [];
@endphp

@section('content')
    @if($isPreview ?? false)
        <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
            Náhled — tento profil zatím není publikovaný.
        </div>
    @endif

    {{-- Breadcrumb --}}
    <nav aria-label="Drobečková navigace" class="border-b border-line bg-white">
        <div class="ff-container flex items-center gap-2 py-4 text-sm text-neutral-500">
            <a href="{{ route('home') }}" class="transition hover:text-primary">Domov</a>
            <x-lucide name="chevron-right" class="h-4 w-4 text-neutral-300" />
            <a href="{{ url('/o-nas') }}" class="transition hover:text-primary">O nás</a>
            <x-lucide name="chevron-right" class="h-4 w-4 text-neutral-300" />
            <span class="font-medium text-neutral-900">{{ $name }}</span>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="bg-surface-alt">
        <div class="ff-container flex flex-col items-start gap-12 py-16 lg:flex-row lg:gap-16 lg:py-20">
            <div class="aspect-[3/4] w-full max-w-[380px] shrink-0 overflow-hidden rounded-2xl bg-white lg:h-[480px] lg:w-[380px]">
                @if($photo)
                    <img src="{{ $photo }}" alt="{{ $name }}" class="h-full w-full object-cover">
                @endif
            </div>

            <div class="flex flex-1 flex-col gap-6">
                @if($therapist->badge)
                    <span class="inline-flex w-fit items-center rounded-full bg-primary-light px-3.5 py-1.5 font-heading text-xs font-semibold text-primary-dark">{{ $therapist->badge }}</span>
                @endif

                <div class="flex flex-col gap-2">
                    <h1 class="font-heading text-4xl font-bold leading-tight text-neutral-900">{{ $name }}</h1>
                    @if($therapist->title)
                        <p class="text-lg text-neutral-600">{{ $therapist->title }}</p>
                    @endif
                </div>

                @if($therapist->specializations->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach($therapist->specializations as $specialization)
                            <span class="inline-flex items-center rounded-full border border-line bg-white px-4 py-2 text-sm font-medium text-neutral-900">{{ $specialization->name }}</span>
                        @endforeach
                    </div>
                @endif

                <span class="h-0.5 w-[60px] bg-primary"></span>

                <div class="flex flex-col gap-3 text-[15px] text-neutral-900">
                    @if($phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="inline-flex items-center gap-2.5 transition hover:text-primary">
                            <x-lucide name="phone" class="h-[18px] w-[18px] text-primary" />
                            {{ $phone }}
                        </a>
                    @endif
                    @if($email)
                        <a href="mailto:{{ $email }}" class="inline-flex items-center gap-2.5 transition hover:text-primary">
                            <x-lucide name="mail" class="h-[18px] w-[18px] text-primary" />
                            {{ $email }}
                        </a>
                    @endif
                    @if($address)
                        <span class="inline-flex items-center gap-2.5">
                            <x-lucide name="map-pin" class="h-[18px] w-[18px] text-primary" />
                            {{ $address }}
                        </span>
                    @endif
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-7 py-3.5 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark">
                        <x-lucide name="calendar" class="h-[18px] w-[18px]" />
                        Objednat se
                    </a>
                    @if($phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="inline-flex items-center justify-center gap-2.5 rounded-full border-[1.5px] border-primary bg-white px-7 py-3.5 font-heading text-sm font-semibold text-primary transition hover:bg-primary-light">
                            Zavolat
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Biography --}}
    @if(! empty($therapist->bio))
        <section class="bg-white py-16 lg:py-20">
            <div class="ff-container flex flex-col gap-6">
                <h2 class="font-heading text-3xl font-bold text-neutral-900">O mně</h2>
                <div class="ff-prose max-w-3xl text-neutral-600">{!! $therapist->bio !!}</div>
            </div>
        </section>
    @endif

    {{-- Education & Certifications --}}
    @if(! empty($education) || ! empty($certifications))
        <section class="bg-surface-alt py-16 lg:py-20">
            <div class="ff-container grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
                @if(! empty($education))
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center gap-3">
                            <x-lucide name="graduation-cap" class="h-6 w-6 text-primary" />
                            <h2 class="font-heading text-2xl font-bold text-neutral-900">Vzdělání</h2>
                        </div>
                        <div class="flex flex-col gap-4">
                            @foreach($education as $item)
                                <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-6 py-5">
                                    <p class="font-heading text-base font-semibold text-neutral-900">{{ $item['degree'] ?? '' }}</p>
                                    @if(! empty($item['institution']))
                                        <p class="text-sm text-neutral-600">{{ $item['institution'] }}</p>
                                    @endif
                                    @if(! empty($item['period']))
                                        <p class="text-[13px] text-neutral-500">{{ $item['period'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(! empty($certifications))
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center gap-3">
                            <x-lucide name="award" class="h-6 w-6 text-primary" />
                            <h2 class="font-heading text-2xl font-bold text-neutral-900">Vybrané certifikace a kurzy</h2>
                        </div>
                        <div class="flex flex-col gap-4">
                            @foreach($certifications as $item)
                                <div class="flex flex-col gap-0.5 rounded-xl border border-line bg-white px-6 py-5">
                                    <p class="font-heading text-[15px] font-semibold text-neutral-900">{{ $item['name'] ?? '' }}</p>
                                    @if(! empty($item['institution']))
                                        <p class="text-[13px] text-neutral-500">{{ $item['institution'] }}</p>
                                    @endif
                                    @if(! empty($item['year']))
                                        <p class="text-xs font-semibold text-neutral-500">{{ $item['year'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Areas of specialization --}}
    @if($therapist->specializations->isNotEmpty())
        <section class="bg-white py-16 lg:py-24">
            <div class="ff-container">
                @include('bricks.partials.heading', ['config' => [
                    'eyebrow' => 'Oblasti specializace',
                    'title' => 'V čem vám mohu pomoci',
                ]])

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($therapist->specializations as $specialization)
                        <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface-alt p-6 text-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
                                {!! \App\Support\Icon::render($specialization->icon ?: 'heart', 'h-6 w-6') !!}
                            </div>
                            <h3 class="font-heading text-base font-semibold text-neutral-900">{{ $specialization->name }}</h3>
                            @if($specialization->description)
                                <p class="text-sm leading-relaxed text-neutral-600">{{ $specialization->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA --}}
    <section class="bg-primary-light py-16">
        <div class="ff-container flex flex-col items-center gap-6 text-center">
            <h2 class="font-heading text-3xl font-bold text-neutral-900">Chcete se objednat k {{ $name }}?</h2>
            <p class="max-w-2xl leading-relaxed text-neutral-600">Rezervujte si termín online nebo nás kontaktujte telefonicky.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-primary px-9 py-[18px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                    <x-lucide name="calendar" class="h-5 w-5" />
                    Objednat se
                </a>
                @if($phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="inline-flex items-center justify-center gap-2.5 rounded-full border-[1.5px] border-primary bg-white px-9 py-[18px] font-heading text-base font-semibold text-primary transition hover:bg-white/70">
                        Zavolat
                    </a>
                @endif
            </div>
        </div>
    </section>
@endsection
