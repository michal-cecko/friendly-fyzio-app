<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages;

use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\OneOffEventResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendBulkParticipantEmailAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use App\Models\OneOffEvent;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOneOffEvent extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = OneOffEventResource::class;

    public function getTitle(): string
    {
        /** @var OneOffEvent $record */
        $record = $this->getRecord();

        return 'Jednorázová akce '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            // The catch-all dropdown always sits last in the header.
            ActionGroup::make([
                SendBulkParticipantEmailAction::make(),
                PresaleLinkAction::make(),
                SendOfferInvitationAction::make(),
                DeleteAction::make(),
                ActivityLogAction::make(),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
