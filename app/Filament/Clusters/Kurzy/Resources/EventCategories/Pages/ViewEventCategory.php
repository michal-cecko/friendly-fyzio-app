<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Models\EventCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewEventCategory extends ViewRecord
{
    protected static string $resource = EventCategoryResource::class;

    public function getTitle(): string
    {
        /** @var EventCategory $record */
        $record = $this->getRecord();

        return 'Kategorie akcí '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            OpenPublicPageAction::make(),
            EditAction::make(),
            // The catch-all dropdown always sits last in the header.
            ActionGroup::make([
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
