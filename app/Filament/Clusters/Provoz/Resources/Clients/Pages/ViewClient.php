<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Pages;

use App\Filament\Clusters\Provoz\Resources\Clients\Actions\AdjustCreditAction;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Clients\Widgets\ClientStatsOverview;
use App\Filament\Pages\Calendar;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\ResetPasswordAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use STS\FilamentImpersonate\Actions\Impersonate;

class ViewClient extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()->record($this->getRecord()),
            Action::make('createReservation')
                ->label('Vytvořit rezervaci')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->url(Calendar::getUrl()),
            AdjustCreditAction::make()->record($this->getRecord()),
            ResetPasswordAction::make(),
            EditAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientStatsOverview::class,
        ];
    }
}
