@php($config ??= [])

<section class="py-16 lg:py-20">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="overflow-hidden rounded-3xl bg-neutral-900 px-8 py-14 lg:px-16">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-heading text-3xl font-bold text-white lg:text-4xl">{{ $config['title'] ?? 'Newsletter' }}</h2>
                @if(! empty($config['subtitle']))
                    <p class="mx-auto mt-4 max-w-xl text-neutral-300">{{ $config['subtitle'] }}</p>
                @endif

                @if(session('newsletter_success'))
                    <p class="mx-auto mt-8 inline-block rounded-full bg-primary/20 px-6 py-3 font-medium text-primary-light">Děkujeme! Brzy se vám ozveme.</p>
                @else
                    <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row">
                        @csrf
                        <input type="email" name="email" required placeholder="Váš e-mail" class="w-full rounded-full border-0 px-5 py-3.5 text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-primary">
                        <button type="submit" class="rounded-full bg-primary px-7 py-3.5 font-semibold text-white transition hover:bg-primary-dark">{{ $config['button_text'] ?? 'Odebírat' }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
