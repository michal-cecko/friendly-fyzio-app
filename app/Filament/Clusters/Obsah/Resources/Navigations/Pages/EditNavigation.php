<?php

namespace App\Filament\Clusters\Obsah\Resources\Navigations\Pages;

use App\Filament\Clusters\Obsah\Resources\Navigations\NavigationResource;
use App\Models\Navigation;
use Filament\Resources\Pages\EditRecord;

class EditNavigation extends EditRecord
{
    protected static string $resource = NavigationResource::class;

    public function getHeading(): string
    {
        /** @var Navigation $record */
        $record = $this->getRecord();

        return parent::getHeading().' - '.$record->location->getLabel();
    }
}
