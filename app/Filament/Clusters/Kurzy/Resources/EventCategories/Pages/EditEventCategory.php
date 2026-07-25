<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Models\EventCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

class EditEventCategory extends BaseEditRecord
{
    protected static string $resource = EventCategoryResource::class;

    public function getTitle(): string
    {
        /** @var EventCategory $record */
        $record = $this->getRecord();

        return 'Upravit kategorii akcí '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            OpenPublicPageAction::make(),
            $this->getSaveHeaderAction(),
            // The catch-all dropdown always sits last in the header.
            ActionGroup::make([
                ViewAction::make(),
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
