<?php

namespace App\Filament\Clusters\System\Resources\Users\RelationManagers;

use App\Filament\Clusters\Provoz\Resources\StaffProfiles\Schemas\StaffProfileForm;
use App\Models\StaffProfile;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StaffProfileRelationManager extends RelationManager
{
    protected static string $relationship = 'staffProfile';

    protected static ?string $title = 'Veřejný profil';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && $ownerRecord->isTherapist();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(StaffProfileForm::components(withUser: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('slug')
            ->columns([
                TextColumn::make('title')
                    ->label('Pozice')
                    ->placeholder('—'),
                IconColumn::make('published')
                    ->label('Publikováno')
                    ->boolean()
                    ->getStateUsing(fn (StaffProfile $record): bool => $record->isPublished()),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->since(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Vytvořit profil')
                    ->visible(fn (): bool => $this->getOwnerRecord()->staffProfile()->doesntExist()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
