<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\CourseEnrollmentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Lessons the client actually turned up for — the presence list holds a row
     * per lesson, so an unscoped count would just repeat the série's length.
     */
    protected function extraColumns(): array
    {
        return [
            TextColumn::make('attended_count')
                ->label('Účast')
                ->counts([
                    'attendances as attended_count' => fn (Builder $query) => $query->where('attended', true),
                ]),
        ];
    }

    protected function detailUrl(Model $record): string
    {
        return CourseEnrollmentResource::getUrl('view', ['record' => $record]);
    }
}
