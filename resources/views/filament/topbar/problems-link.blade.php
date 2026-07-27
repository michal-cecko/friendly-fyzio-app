@php
    use App\Filament\Pages\Problems;

    $canAccess = Problems::canAccess();
    $badge = $canAccess ? Problems::getNavigationBadge() : null;
@endphp

@if ($canAccess)
    {{-- Always rendered for staff, badge or not: a toolbar icon that comes and
         goes is harder to find than a quiet one. --}}
    <x-filament::icon-button
        :href="Problems::getUrl()"
        tag="a"
        icon="heroicon-o-exclamation-triangle"
        color="gray"
        label="Problémy"
        tooltip="Problémy"
        :badge="$badge"
        :badge-color="Problems::getNavigationBadgeColor()"
    />
@endif
