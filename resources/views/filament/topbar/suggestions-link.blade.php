@php
    use App\Filament\Pages\Suggestions;

    $canAccess = Suggestions::canAccess();
    $badge = $canAccess ? Suggestions::getNavigationBadge() : null;
@endphp

@if ($canAccess)
    <x-filament::icon-button
        :href="Suggestions::getUrl()"
        tag="a"
        icon="heroicon-o-light-bulb"
        color="gray"
        label="Návrhy"
        tooltip="Návrhy"
        :badge="$badge"
        :badge-color="Suggestions::getNavigationBadgeColor()"
    />
@endif
