@php
    use App\Support\Settings;
    $columns = $footerNav?->items ?? collect();
    $siteName = Settings::get('web.site_name', 'Friendly Fyzio');
    $email = Settings::get('web.contact_email');
    $phone = Settings::get('web.contact_phone');
    $address = Settings::get('web.address');
    $instagram = Settings::get('web.instagram_url');
    $facebook = Settings::get('web.facebook_url');
    $note = Settings::get('web.footer_note');
@endphp

<footer class="bg-neutral-900 text-neutral-300">
    <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
        <div class="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <img src="{{ asset('logo/ff-logo-dark.svg') }}" alt="{{ $siteName }}" class="h-9 w-auto">
                @if($note)
                    <p class="mt-4 max-w-xs text-sm leading-relaxed text-neutral-400">{{ $note }}</p>
                @endif
                @if($instagram || $facebook)
                    <div class="mt-5 flex gap-3">
                        @if($instagram)
                            <a href="{{ $instagram }}" target="_blank" rel="noopener" aria-label="Instagram" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-primary hover:text-white">
                                {!! rescue(fn () => svg('heroicon-o-camera', 'h-5 w-5')->toHtml(), '', false) !!}
                            </a>
                        @endif
                        @if($facebook)
                            <a href="{{ $facebook }}" target="_blank" rel="noopener" aria-label="Facebook" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-primary hover:text-white">
                                {!! rescue(fn () => svg('heroicon-o-globe-alt', 'h-5 w-5')->toHtml(), '', false) !!}
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            @foreach($columns as $column)
                <div>
                    <h3 class="font-heading text-sm font-bold uppercase tracking-wide text-white">{{ $column->label }}</h3>
                    <ul class="mt-4 space-y-2.5">
                        @foreach($column->children as $child)
                            <li>
                                <a href="{{ $child->resolvedUrl() ?? '#' }}" target="{{ $child->target }}" class="text-sm text-neutral-400 transition hover:text-primary">{{ $child->label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div>
                <h3 class="font-heading text-sm font-bold uppercase tracking-wide text-white">Kontakt</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-neutral-400">
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

        <div class="mt-12 border-t border-white/10 pt-8 text-sm text-neutral-500">
            &copy; {{ now()->year }} {{ $siteName }}. Všechna práva vyhrazena.
        </div>
    </div>
</footer>
