<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Schemas;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\Course;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
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
                            ->placeholder('—')
                            ->url(fn (Course $record): ?string => $record->category !== null
                                ? CourseCategoryResource::getUrl('view', ['record' => $record->category])
                                : null),
                        TextEntry::make('instructor.name')
                            ->label('Lektor')
                            ->placeholder('—')
                            ->url(fn (Course $record): ?string => $record->instructor !== null
                                ? UserResource::getUrl('view', ['record' => $record->instructor])
                                : null),
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
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
