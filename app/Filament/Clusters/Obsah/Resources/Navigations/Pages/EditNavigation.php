<?php

namespace App\Filament\Clusters\Obsah\Resources\Navigations\Pages;

use App\Filament\Clusters\Obsah\Resources\Navigations\NavigationResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Navigation;

class EditNavigation extends BaseEditRecord
{
    protected static string $resource = NavigationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivityLogAction::make(),
        ];
    }

    public function getTitle(): string
    {
        /** @var Navigation $record */
        $record = $this->getRecord();

        return 'Upravit menu — '.$record->location->getLabel();
    }
}
