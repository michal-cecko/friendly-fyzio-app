<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CourseEnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'courseEnrollments';

    protected static ?string $title = 'Kurzy';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedAcademicCap;

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['series.course']))
            ->columns([
                TextColumn::make('series.course.name')
                    ->label('Kurz'),
                TextColumn::make('series.name')
                    ->label('Běh'),
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
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
