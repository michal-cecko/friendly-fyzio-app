<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php($page = $page ?? null)
    @php($seo = $seo ?? [])
    @php($seoTitle = ($seo['title'] ?? null) ?: ($page?->meta_title ?: $page?->title ?: config('app.name')))
    @php($seoDescription = ($seo['description'] ?? null) ?: $page?->meta_description)
    @php($ogImage = ($seo['image'] ?? null) ?: ($page ? \App\Support\Media::url($page->featured_image, '800') : null))

    <title>{{ $seoTitle }} | Friendly Fyzio</title>
    @if(! empty($seoDescription))
        <meta name="description" content="{{ $seoDescription }}">
    @endif

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    @if(! empty($seoDescription))
        <meta property="og:description" content="{{ $seoDescription }}">
    @endif
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=montserrat:400,500,600,700,800|open-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-neutral-900 antialiased">
    <x-site.banners :page-id="$seo['banner_page_id'] ?? ($page?->id)" />

    <x-site.header :admin-edit-url="$adminEditUrl ?? null" />

    <main class="flex-1">
        @yield('content')
    </main>

    <x-site.footer />
</body>
</html>
