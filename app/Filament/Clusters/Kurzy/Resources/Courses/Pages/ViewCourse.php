<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Pages;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\Course;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCourse extends ViewRecord
{
    use HasCourseBreadcrumbs;

    protected static string $resource = CourseResource::class;

    public function getTitle(): string
    {
        /** @var Course $record */
        $record = $this->getRecord();

        return 'Kurz '.$record->name;
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
