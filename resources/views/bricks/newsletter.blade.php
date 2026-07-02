@php($config ??= [])

<section class="bg-white py-16 lg:py-20">
    <div class="ff-container">
        <div class="flex flex-col items-center gap-6 rounded-2xl bg-primary-light px-8 py-12 text-center lg:px-16">
            <div class="flex max-w-2xl flex-col gap-2">
                <h2 class="font-heading text-2xl font-bold text-neutral-900">{!! \App\Support\RichText::inline($config['title'] ?? 'Přihlaste se k odběru novinek') !!}</h2>
                @if(! empty($config['subtitle']))
                    <p class="text-[15px] text-neutral-600">{!! \App\Support\RichText::inline($config['subtitle']) !!}</p>
                @endif
            </div>

            <livewire:newsletter-form
                :placeholder="$config['placeholder'] ?? 'Váš e-mail'"
                :button-text="$config['button_text'] ?? 'Odebírat'"
            />

            <p class="text-xs text-neutral-500">{!! \App\Support\RichText::inline($config['consent'] ?? 'Odesláním souhlasím se zpracováním osobních údajů.') !!}</p>
        </div>
    </div>
</section>
