@php
    use App\Support\Settings;

    $config ??= [];
    $formTitle = $config['form_title'] ?? 'Napište nám';
    $buttonText = $config['form_button_text'] ?? 'Odeslat zprávu';

    $phone = Settings::get('web.contact_phone');
    $email = Settings::get('web.contact_email');
    $address = Settings::get('web.address');
    $hours = Settings::get('web.opening_hours');

    $mapUrl = $config['map_embed_url'] ?? null;
    if (blank($mapUrl) && filled($address)) {
        $mapUrl = 'https://www.google.com/maps?q='.urlencode($address).'&output=embed';
    }

    $details = array_values(array_filter([
        $phone ? ['icon' => 'phone', 'label' => 'Telefon', 'value' => $phone, 'href' => 'tel:'.preg_replace('/\s+/', '', $phone)] : null,
        $email ? ['icon' => 'mail', 'label' => 'E-mail', 'value' => $email, 'href' => 'mailto:'.$email] : null,
        $address ? ['icon' => 'map-pin', 'label' => 'Adresa', 'value' => $address, 'href' => null] : null,
        $hours ? ['icon' => 'clock', 'label' => 'Otevírací hodiny', 'value' => $hours, 'href' => null] : null,
    ]));
@endphp

@if(! empty($config['eyebrow']) || ! empty($config['title']) || ! empty($config['subtitle']))
    <section class="bg-surface-alt py-14 lg:py-16">
        <div class="ff-container flex flex-col items-center gap-3 text-center">
            @if(! empty($config['eyebrow']))
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.12em] text-primary">{{ $config['eyebrow'] }}</p>
            @endif
            @if(! empty($config['title']))
                <h1 class="font-heading text-3xl font-bold text-neutral-900 lg:text-4xl">{!! \App\Support\RichText::inline($config['title']) !!}</h1>
            @endif
            @if(! empty($config['subtitle']))
                <p class="max-w-2xl leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($config['subtitle']) !!}</p>
            @endif
        </div>
    </section>
@endif

<section class="py-16 lg:py-24">
    <div class="ff-container grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
        <div class="flex flex-col gap-6">
            <h2 class="font-heading text-2xl font-bold text-neutral-900">{{ $formTitle }}</h2>
            <livewire:contact-form :button-text="$buttonText" />
        </div>

        <div class="flex flex-col gap-8">
            @if($details)
                <ul class="flex flex-col gap-5">
                    @foreach($details as $item)
                        <li class="flex items-start gap-4">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-light text-primary">
                                {!! \App\Support\Icon::render($item['icon'], 'h-5 w-5') !!}
                            </span>
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-neutral-500">{{ $item['label'] }}</span>
                                @if($item['href'])
                                    <a href="{{ $item['href'] }}" class="font-medium text-neutral-900 transition hover:text-primary">{{ $item['value'] }}</a>
                                @else
                                    <span class="font-medium text-neutral-900">{{ $item['value'] }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($mapUrl)
                <div class="overflow-hidden rounded-2xl border border-line">
                    <iframe
                        src="{{ $mapUrl }}"
                        title="Mapa – {{ $address }}"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        class="h-64 w-full lg:h-72"
                        style="border:0"
                    ></iframe>
                </div>
            @endif
        </div>
    </div>
</section>
