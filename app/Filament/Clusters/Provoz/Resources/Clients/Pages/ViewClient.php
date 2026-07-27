<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Pages;

use App\Filament\Clusters\Provoz\Resources\Clients\Actions\AdjustCreditAction;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Clients\Widgets\ClientStatsOverview;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CreateReservationAction;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\ResetPasswordAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use STS\FilamentImpersonate\Actions\Impersonate;

class ViewClient extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = ClientResource::class;

    public function getTitle(): string
    {
        /** @var User $record */
        $record = $this->getRecord();

        return 'Klient '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (User $record): bool => ClientResource::canManageClient($record)),
            ActionGroup::make([
                CreateReservationAction::make()->client($this->getRecord()),
                AdjustCreditAction::make()->record($this->getRecord()),
                Impersonate::make()->record($this->getRecord()),
                ResetPasswordAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => ClientResource::canManageClient($record)),
                ActivityLogAction::make(),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientStatsOverview::class,
        ];
    }
}
