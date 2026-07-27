@php
    use App\Support\Settings;

    $address = Settings::get('web.address');
    $phone = Settings::get('web.contact_phone');
    $email = Settings::get('web.contact_email');

    $brand = 'FriendlyFyzio s.r.o.';
    $footerAddress = filled($address) ? "{$brand} | {$address}" : $brand;
    $footerContact = implode('  •  ', array_filter([$phone, $email]));
@endphp
<!DOCTYPE html>
<html lang="cs">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Náhled e-mailu</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=montserrat:400,500,600,700,800|open-sans:400,500,600,700" rel="stylesheet" />

        @vite('resources/css/app.css')
        @masonStyles

        <style>
            body { background-color: #F5F5F5; margin: 0; padding: 24px 12px; }
            .e-shell { width: 100%; max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E5E5E5; overflow: hidden; }
            .e-head { background-color: #FFF8FA; padding: 32px 40px 24px; text-align: center; font-family: 'Montserrat', Arial, sans-serif; }
            .e-head-logo { font-size: 22px; font-weight: 500; color: #1A1A1A; }
            .e-head-logo em { font-weight: 600; font-style: italic; color: #ED86A3; }
            .e-rule { height: 3px; background-color: #ED86A3; }
            .e-content { padding: 40px 48px; }
            .e-foot { background-color: #F5F5F5; padding: 24px 48px; text-align: center; font-family: 'Open Sans', Arial, sans-serif; }
            .e-foot-text { font-size: 11px; color: #888888; padding-bottom: 6px; }
        </style>
    </head>
    <body class="font-sans text-neutral-900 antialiased">
        <div class="e-shell">
            <div class="e-head">
                <span class="e-head-logo">Friendly<em>Fyzio</em></span>
            </div>
            <div class="e-rule"></div>
            <div class="e-content">
                @include('mason::iframe-preview-content', ['blocks' => $blocks])
            </div>
            <div class="e-foot">
                <div class="e-foot-text">{{ $footerAddress }}</div>
                @if(filled($footerContact))
                    <div class="e-foot-text">{{ $footerContact }}</div>
                @endif
            </div>
        </div>
    </body>
</html>
