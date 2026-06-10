<?php

namespace App\Filament\Resources\Rooms\RelationManagers;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\RoomBlocking;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlockingsRelationManager extends RelationManager
{
    protected static string $relationship = 'blockings';

    protected static ?string $title = 'Blokace';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reason')
                    ->label('Důvod')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Toggle::make('is_recurring')
                    ->label('Opakovat')
                    ->live()
                    ->default(false)
                    ->columnSpanFull(),
                Select::make('day_of_week')
                    ->label('Den v týdnu')
                    ->options(DayOfWeek::class)
                    ->required()
                    ->visible(fn (Get $get): bool => (bool) $get('is_recurring')),
                Select::make('week_type')
                    ->label('Typ týdne')
                    ->options(WeekType::class)
                    ->default('all')
                    ->visible(fn (Get $get): bool => (bool) $get('is_recurring')),
                TimePicker::make('start_time')
                    ->label('Od')
                    ->seconds(false)
                    ->required()
                    ->visible(fn (Get $get): bool => (bool) $get('is_recurring')),
                TimePicker::make('end_time')
                    ->label('Do')
                    ->seconds(false)
                    ->required()
                    ->after('start_time')
                    ->visible(fn (Get $get): bool => (bool) $get('is_recurring')),
                DateTimePicker::make('start_at')
                    ->label('Začátek')
                    ->seconds(false)
                    ->native(false)
                    ->required()
                    ->visible(fn (Get $get): bool => ! $get('is_recurring')),
                DateTimePicker::make('end_at')
                    ->label('Konec')
                    ->seconds(false)
                    ->native(false)
                    ->required()
                    ->after('start_at')
                    ->visible(fn (Get $get): bool => ! $get('is_recurring')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                IconColumn::make('is_recurring')
                    ->label('Opakované')
                    ->boolean(),
                TextColumn::make('schedule')
                    ->label('Kdy')
                    ->state(fn (RoomBlocking $record): string => $record->is_recurring
                        ? trim(($record->day_of_week?->getLabel() ?? '').' '.$record->start_time.'–'.$record->end_time)
                        : (($record->start_at?->format('d.m.Y H:i') ?? '').' – '.($record->end_at?->format('d.m.Y H:i') ?? ''))),
                TextColumn::make('reason')
                    ->label('Důvod')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
