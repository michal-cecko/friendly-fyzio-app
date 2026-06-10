<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Název'),
                        TextEntry::make('slug')
                            ->label('Slug'),
                        TextEntry::make('category.name')
                            ->label('Kategorie')
                            ->placeholder('—'),
                        TextEntry::make('instructor.name')
                            ->label('Lektor')
                            ->placeholder('—'),
                        TextEntry::make('description')
                            ->label('Popis')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('max_substitutions')
                            ->label('Max. náhrad'),
                        TextEntry::make('early_cancel_hours')
                            ->label('Včasné zrušení (hodin předem)')
                            ->suffix(' h'),
                        TextEntry::make('published_at')
                            ->label('Publikováno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
