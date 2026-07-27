<?php

namespace App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages;

use App\Filament\Clusters\Obsah\Resources\InstagramConnections\InstagramConnectionResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\InstagramConnection;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Icons\Heroicon;

class EditInstagramConnection extends BaseEditRecord
{
    protected static string $resource = InstagramConnectionResource::class;

    public function getTitle(): string
    {
        /** @var InstagramConnection $record */
        $record = $this->getRecord();

        return 'Upravit Instagram účet @'.$record->username;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Save stays a standalone header button (prepended by BaseEditRecord);
            // everything else lives in the catch-all dropdown, which sits last.
            ActionGroup::make([
                InstagramConnectionResource::authorizeAction(),
                InstagramConnectionResource::syncAction(),
                ActivityLogAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
