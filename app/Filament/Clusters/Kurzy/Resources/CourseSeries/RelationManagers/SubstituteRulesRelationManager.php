<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers;

use App\Models\CourseSeries;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where this série's substitute entries may be redeemed: a client excused in time
 * from a lesson gets a token, and the client zone offers them free places in the
 * séries listed here (never publicly visible).
 */
class SubstituteRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'substituteRulesAsSource';

    protected static ?string $title = 'Náhrady';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedArrowsRightLeft;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('target_series_id')
                ->label('Náhradu lze uplatnit v sérii')
                ->options(fn (): array => CourseSeries::query()
                    ->with('course')
                    ->whereKeyNot($this->getOwnerRecord()->getKey())
                    ->get()
                    ->sortBy(fn (CourseSeries $series): string => trim(($series->course?->name ?? '').' '.$series->name))
                    ->mapWithKeys(fn (CourseSeries $series): array => [
                        $series->getKey() => trim(($series->course?->name ? $series->course->name.' – ' : '').$series->name),
                    ])
                    ->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->required()
                ->helperText('Klient si bude moci vybrat volný termín v této sérii.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->description('Série, ve kterých si klienti mohou nahradit zmeškanou lekci této série.')
            ->modelLabel('náhradní sérii')
            ->pluralModelLabel('náhradní série')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('targetSeries.course'))
            ->columns([
                TextColumn::make('targetSeries.course.name')
                    ->label('Kurz')
                    ->placeholder('—'),
                TextColumn::make('targetSeries.name')
                    ->label('Série'),
            ])
            ->emptyStateHeading('Žádné náhradní série')
            ->emptyStateDescription('Bez pravidla si klienti nemají kde náhradní vstup uplatnit.')
            ->headerActions([
                CreateAction::make()
                    ->label('Přidat náhradní sérii')
                    ->modalHeading('Přidat náhradní sérii'),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
