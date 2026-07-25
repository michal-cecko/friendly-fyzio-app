<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Schemas;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\CourseEnrollment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseEnrollmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('series.course.name')
                            ->label('Kurz')
                            ->placeholder('—')
                            ->url(fn (CourseEnrollment $record): ?string => $record->series?->course !== null
                                ? CourseResource::getUrl('view', ['record' => $record->series->course])
                                : null),
                        TextEntry::make('series.name')
                            ->label('Série')
                            ->placeholder('—')
                            ->url(fn (CourseEnrollment $record): ?string => $record->series !== null
                                ? CourseSeriesResource::getUrl('view', ['record' => $record->series])
                                : null),
                        TextEntry::make('client.name')
                            ->label('Klient')
                            ->placeholder('—')
                            ->url(fn (CourseEnrollment $record): ?string => $record->client !== null
                                ? ClientResource::getUrl('view', ['record' => $record->client])
                                : null),
                        TextEntry::make('status')
                            ->label('Stav')
                            ->badge(),
                        TextEntry::make('payment_status')
                            ->label('Platba')
                            ->badge(),
                        TextEntry::make('paid_at')
                            ->label('Zaplaceno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
