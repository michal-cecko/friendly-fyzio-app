@php
    use App\Support\Settings;
    $columns = $footerNav?->items ?? collect();
    $siteName = 'Friendly Fyzio';
    $email = Settings::get('web.contact_email');
    $phone = Settings::get('web.contact_phone');
    $address = Settings::get('web.address');
    $instagram = Settings::get('web.instagram_url');
    $facebook = Settings::get('web.facebook_url');
    $note = Settings::get('web.footer_note');
    $companyId = Settings::get('web.company_id');
@endphp

<footer class="bg-neutral-900 text-neutral-300">
    <div class="ff-container py-16">
        <div class="flex flex-col gap-12 lg:flex-row lg:justify-between">
            <div class="max-w-xs">
                <img src="{{ asset('logo/ff-logo-dark.svg') }}" alt="{{ $siteName }}" class="h-9 w-auto">
                @if($note)
                    <p class="mt-4 text-sm leading-relaxed text-neutral-400">{{ $note }}</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-x-16 gap-y-10">
                @foreach($columns as $column)
                    <div>
                        <h3 class="font-heading text-sm font-semibold text-white">{{ $column->label }}</h3>
                        <ul class="mt-4 space-y-3">
                            @foreach($column->children as $child)
                                <li>
                                    <a href="{{ $child->resolvedUrl() ?? '#' }}" target="{{ $child->target }}" class="text-sm text-neutral-400 transition hover:text-primary">{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div>
                    <h3 class="font-heading text-sm font-semibold text-white">Kontakt</h3>
                    <ul class="mt-4 space-y-3 text-sm text-neutral-400">
                        @if($phone)
                            <li><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="transition hover:text-primary">{{ $phone }}</a></li>
                        @endif
                        @if($email)
                            <li><a href="mailto:{{ $email }}" class="transition hover:text-primary">{{ $email }}</a></li>
                        @endif
                        @if($address)
                            <li>{{ $address }}</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-4 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-heading text-[15px] font-semibold text-white">Odebírejte novinky</p>
                <p class="text-[13px] text-neutral-400">Buďte první, kdo se dozví o nových kurzech a akcích.</p>
            </div>
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="flex gap-2.5">
                @csrf
                <input type="email" name="email" required placeholder="Váš e-mail" class="w-48 rounded-full border border-white/20 bg-neutral-900 px-4 py-2.5 text-sm text-white placeholder:text-neutral-500 focus:border-primary focus:outline-none sm:w-56">
                <button type="submit" class="rounded-full bg-primary px-5 py-2.5 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark">Odebírat</button>
            </form>
        </div>

        <div class="mt-8 flex flex-col gap-4 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[13px] text-neutral-500">
                &copy; {{ now()->year }} {{ $siteName }}. Všechna práva vyhrazena.@if($companyId) IČO: {{ $companyId }}@endif
            </p>
            @if($instagram || $facebook)
                <div class="flex gap-4">
                    @if($instagram)
                        <a href="{{ $instagram }}" target="_blank" rel="noopener" aria-label="Instagram" class="text-neutral-400 transition hover:text-primary">
                            <x-lucide name="instagram" class="h-5 w-5" />
                        </a>
                    @endif
                    @if($facebook)
                        <a href="{{ $facebook }}" target="_blank" rel="noopener" aria-label="Facebook" class="text-neutral-400 transition hover:text-primary">
                            <x-lucide name="facebook" class="h-5 w-5" />
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</footer>
