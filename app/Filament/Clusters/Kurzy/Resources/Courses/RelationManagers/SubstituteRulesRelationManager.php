<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\RelationManagers;

use App\Models\Course;
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
 * Where this course's substitute entries may be redeemed: clients who excuse
 * themselves from a lesson in time get a token, and the client zone offers them
 * free places in the courses listed here (never publicly visible).
 */
class SubstituteRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'substituteRulesAsSource';

    protected static ?string $title = 'Náhrady';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedArrowsRightLeft;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('target_course_id')
                ->label('Náhradu lze uplatnit v kurzu')
                ->options(fn (): array => Course::query()
                    ->whereKeyNot($this->getOwnerRecord()->getKey())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->required()
                ->helperText('Klient si bude moci vybrat volný termín v tomto kurzu.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->description('Kurzy, ve kterých si klienti mohou nahradit zmeškanou lekci tohoto kurzu.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('targetCourse'))
            ->columns([
                TextColumn::make('targetCourse.name')
                    ->label('Náhradní kurz'),
                TextColumn::make('targetCourse.category.name')
                    ->label('Kategorie')
                    ->placeholder('—'),
            ])
            ->emptyStateHeading('Žádné náhradní kurzy')
            ->emptyStateDescription('Bez pravidla si klienti nemají kde náhradní vstup uplatnit.')
            ->headerActions([
                CreateAction::make()
                    ->label('Přidat náhradní kurz')
                    ->modalHeading('Přidat náhradní kurz'),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
