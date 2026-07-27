@php
    use App\Filament\Pages\Help;
@endphp

@if (filament()->auth()->check())
    <div class="ff-sidebar-help">
        <ul class="fi-sidebar-nav-groups">
            <x-filament-panels::sidebar.item
                :active="request()->routeIs(Help::getRouteName())"
                icon="heroicon-o-question-mark-circle"
                :url="Help::getUrl()"
            >
                Nápověda
            </x-filament-panels::sidebar.item>
        </ul>
    </div>
@endif
