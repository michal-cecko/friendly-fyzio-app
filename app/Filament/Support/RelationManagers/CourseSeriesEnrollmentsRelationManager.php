<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\CourseEnrollmentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class CourseSeriesEnrollmentsRelationManager extends AbstractSignupsRelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Přihlášení';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    protected function statusOptions(): string
    {
        return CourseEnrollmentStatus::class;
    }

    protected function extraColumns(): array
    {
        return [
            TextColumn::make('attendances_count')
                ->label('Účast')
                ->counts('attendances'),
        ];
    }

    protected function detailUrl(Model $record): string
    {
        return CourseEnrollmentResource::getUrl('view', ['record' => $record]);
    }
}
