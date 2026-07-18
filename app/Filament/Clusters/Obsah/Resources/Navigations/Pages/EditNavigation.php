<?php

namespace App\Filament\Clusters\Obsah\Resources\Navigations\Pages;

use App\Filament\Clusters\Obsah\Resources\Navigations\NavigationResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Navigation;
use Filament\Resources\Pages\EditRecord;

class EditNavigation extends EditRecord
{
    protected static string $resource = NavigationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivityLogAction::make(),
        ];
    }

    public function getHeading(): string
    {
        /** @var Navigation $record */
        $record = $this->getRecord();

        return parent::getHeading().' - '.$record->location->getLabel();
    }
}
