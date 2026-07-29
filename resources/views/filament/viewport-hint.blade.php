{{-- Keeps the window width in a cookie so the server can render viewport-dependent
     layout on the first paint. See App\Filament\Support\Viewport. --}}
<script>
    (() => {
        const write = () => {
            document.cookie = '{{ \App\Filament\Support\Viewport::COOKIE }}=' + window.innerWidth
                + ';path=/;max-age=31536000;SameSite=Lax';
        };

        write();
        window.addEventListener('resize', write, { passive: true });
    })();
</script>
