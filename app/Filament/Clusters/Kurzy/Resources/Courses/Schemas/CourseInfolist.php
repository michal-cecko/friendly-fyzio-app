<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Schemas;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\Course;
use App\Support\Media;
use Filament\Infolists\Components\ImageEntry;
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
                        ImageEntry::make('featured_image')
                            ->label('Fotka')
                            ->state(fn (Course $record): ?string => Media::url($record->featured_image, '400'))
                            ->visible(fn (Course $record): bool => filled($record->featured_image))
                            ->columnSpanFull(),
                        TextEntry::make('name')
                            ->label('Název'),
                        TextEntry::make('slug')
                            ->label('URL název'),
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
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('max_substitutions')
                            ->label('Max. náhrad'),
                        TextEntry::make('early_cancel_hours')
                            ->label('Včasné zrušení (hodin předem)')
                            ->suffix(' h'),
                        TextEntry::make('drop_in_price')
                            ->label('Cena jednorázového vstupu')
                            ->suffix(' Kč')
                            ->placeholder('Lekce se jednotlivě neprodávají'),
                        TextEntry::make('published_at')
                            ->label('Publikováno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
