@props(['pageId' => null])

@php
    $topbar = \App\Models\Banner::resolve(\App\Enums\BannerType::Topbar, $pageId);
    $floating = \App\Models\Banner::resolve(\App\Enums\BannerType::Floating, $pageId);
    $popup = \App\Models\Banner::resolve(\App\Enums\BannerType::Popup, $pageId);
@endphp

@if($topbar)
    @include('components.banners.topbar', ['banner' => $topbar])
@endif

@if($floating)
    @include('components.banners.floating', ['banner' => $floating])
@endif

@if($popup)
    @include('components.banners.popup', ['banner' => $popup])
@endif
