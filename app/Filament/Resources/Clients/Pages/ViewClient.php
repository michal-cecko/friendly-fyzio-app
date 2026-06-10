<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Pages\Calendar;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Widgets\ClientStatsOverview;
use App\Filament\Support\Actions\ResetPasswordAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewClient extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createReservation')
                ->label('Vytvořit rezervaci')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->url(Calendar::getUrl()),
            ResetPasswordAction::make(),
            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientStatsOverview::class,
        ];
    }
}
