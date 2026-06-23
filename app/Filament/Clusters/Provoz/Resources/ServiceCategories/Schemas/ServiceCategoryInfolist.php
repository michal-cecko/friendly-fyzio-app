<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Filament\Support\Schemas\ResponsiveColumns;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ServiceCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                RecordTimestampsSection::firstRow(
                    Section::make('Kategorie')
                        ->icon(Heroicon::OutlinedTag)
                        ->gridContainer()
                        ->columns(ResponsiveColumns::DENSE)
                        ->schema([
                            TextEntry::make('name')->label('Název'),
                            TextEntry::make('slug')->label('Slug'),
                            TextEntry::make('type')->label('Typ')->badge()->placeholder('—'),
                            TextEntry::make('published_at')
                                ->label('Publikováno')
                                ->dateTime('d.m.Y H:i')
                                ->placeholder('Nepublikováno'),
                            TextEntry::make('description')
                                ->label('Popis')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
                ),
            ]);
    }
}
