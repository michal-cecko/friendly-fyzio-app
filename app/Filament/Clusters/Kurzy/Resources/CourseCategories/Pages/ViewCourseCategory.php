<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\CourseCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCourseCategory extends ViewRecord
{
    protected static string $resource = CourseCategoryResource::class;

    public function getTitle(): string
    {
        /** @var CourseCategory $record */
        $record = $this->getRecord();

        return 'Kategorie kurzu '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
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
