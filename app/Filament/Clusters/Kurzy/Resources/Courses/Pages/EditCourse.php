<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Pages;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\Course;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

class EditCourse extends BaseEditRecord
{
    use HasCourseBreadcrumbs;

    protected static string $resource = CourseResource::class;

    public function getTitle(): string
    {
        /** @var Course $record */
        $record = $this->getRecord();

        return 'Upravit kurz '.$record->name;
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
                ForceDeleteAction::make(),
                RestoreAction::make(),
                ActivityLogAction::make(),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
