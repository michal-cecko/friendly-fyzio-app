@props(['model' => 'turnstileToken'])

{{-- Cloudflare Turnstile for plain Livewire forms (the Filament-field variant
     lives in forms/components/turnstile.blade.php). Writes the token into the
     given Livewire property; invisible until a challenge is needed. --}}
<div
    wire:ignore
    x-data="{
        loadScript() {
            return new Promise((resolve) => {
                if (window.turnstile) { resolve(); return; }
                let existing = document.getElementById('cf-turnstile-script');
                if (existing) {
                    let poll = setInterval(() => {
                        if (window.turnstile) { clearInterval(poll); resolve(); }
                    }, 50);
                    return;
                }
                let s = document.createElement('script');
                s.id = 'cf-turnstile-script';
                s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
                s.async = true;
                s.defer = true;
                s.onload = () => resolve();
                document.head.appendChild(s);
            });
        },
        async render() {
            await this.loadScript();
            window.turnstile.render(this.$refs.widget, {
                sitekey: @js(config('services.turnstile.site_key')),
                appearance: 'interaction-only',
                callback: (t) => { $wire.set('{{ $model }}', t, false); },
                'expired-callback': () => { $wire.set('{{ $model }}', null, false); },
                'error-callback': () => { $wire.set('{{ $model }}', null, false); },
            });
        },
    }"
    x-init="render()"
>
    <div x-ref="widget"></div>
</div>
