<!DOCTYPE html>
<html lang="cs">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Mason Preview</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=montserrat:400,500,600,700,800|open-sans:400,500,600,700" rel="stylesheet" />

        @vite('resources/css/app.css')
        @masonStyles
    </head>
    <body class="bg-white font-sans text-neutral-900 antialiased">
        @include('mason::iframe-preview-content', ['blocks' => $blocks])
    </body>
</html>
