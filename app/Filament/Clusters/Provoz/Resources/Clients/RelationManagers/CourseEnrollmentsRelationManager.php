<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers;

use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Models\CourseEnrollment;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CourseEnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'courseEnrollments';

    protected static ?string $title = 'Kurzy';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedAcademicCap;

    /**
     * Every row here opens an enrollment, so the tab follows the same rule as
     * the resource behind it: a therapist who does not teach never sees it.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return CourseEnrollmentResource::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['series.course']))
            ->columns([
                TextColumn::make('series.course.name')
                    ->label('Kurz')
                    ->searchable(),
                TextColumn::make('series.name')
                    ->label('Série')
                    ->searchable(),
                TextColumn::make('series.start_date')
                    ->label('Od')
                    ->date('d.m.Y'),
                TextColumn::make('series.end_date')
                    ->label('Do')
                    ->date('d.m.Y'),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Platba')
                    ->badge(),
                TextColumn::make('attendances_count')
                    ->label('Účast')
                    ->counts('attendances'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (CourseEnrollment $record): string => CourseEnrollmentResource::getUrl('view', ['record' => $record]))
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
