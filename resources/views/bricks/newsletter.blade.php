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

            @if(session('newsletter_success'))
                <p class="rounded-full bg-white px-6 py-3 font-medium text-primary-dark">Děkujeme! Brzy se vám ozveme.</p>
            @else
                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="flex w-full max-w-md flex-col gap-3 sm:flex-row sm:justify-center">
                    @csrf
                    <input type="email" name="email" required placeholder="{{ $config['placeholder'] ?? 'Váš e-mail' }}" class="w-full rounded-full border border-line bg-white px-5 py-3.5 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 sm:flex-1">
                    <button type="submit" class="rounded-full bg-primary px-7 py-3.5 font-heading font-semibold text-white transition hover:bg-primary-dark">{{ $config['button_text'] ?? 'Odebírat' }}</button>
                </form>
            @endif

            <p class="text-xs text-neutral-500">{{ $config['consent'] ?? 'Odesláním souhlasím se zpracováním osobních údajů.' }}</p>
        </div>
    </div>
</section>
