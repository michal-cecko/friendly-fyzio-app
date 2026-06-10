<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="{
            token: @entangle($getStatePath()),
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
                    sitekey: @js($getSiteKey()),
                    appearance: 'interaction-only',
                    callback: (t) => { this.token = t; },
                    'expired-callback': () => { this.token = null; },
                    'error-callback': () => { this.token = null; },
                });
            },
        }"
        x-init="render()"
    >
        <div x-ref="widget"></div>
    </div>
</x-dynamic-component>
