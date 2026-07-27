<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Schemas;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Support\AttendancePresenter;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\LessonAttendance;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * One seat on one lesson, in full: who it belongs to, how they came by it,
 * whether we are counting on them, and — when they were excused — what became of
 * the náhrada that excuse bought them.
 */
class LessonAttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Klient')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.name')
                            ->label('Klient')
                            ->placeholder('—')
                            ->url(fn (LessonAttendance $record): ?string => $record->client !== null
                                ? ClientResource::getUrl('view', ['record' => $record->client])
                                : null),
                        TextEntry::make('client.email')
                            ->label('E-mail')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('client.phone')
                            ->label('Telefon')
                            ->icon(Heroicon::OutlinedPhone)
                            ->copyable()
                            ->placeholder('—'),
                    ]),
                Section::make('Přihláška a lekce')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('origin')
                            ->label('Přihláška')
                            ->state(fn (LessonAttendance $record): string => AttendancePresenter::originLabel($record))
                            ->badge()
                            ->color(fn (LessonAttendance $record): string => AttendancePresenter::originColor($record))
                            ->url(fn (LessonAttendance $record): ?string => AttendancePresenter::seatUrl($record)),
                        TextEntry::make('lesson.series.course.name')
                            ->label('Kurz')
                            ->placeholder('—')
                            ->url(fn (LessonAttendance $record): ?string => $record->lesson?->series?->course !== null
                                ? CourseResource::getUrl('view', ['record' => $record->lesson->series->course])
                                : null),
                        TextEntry::make('lesson_id')
                            ->label('Lekce')
                            ->state(fn (LessonAttendance $record): string => AttendancePresenter::lessonLabel($record->lesson))
                            ->url(fn (LessonAttendance $record): ?string => $record->lesson !== null
                                ? LessonResource::getUrl('view', ['record' => $record->lesson])
                                : null),
                        TextEntry::make('enrollment.series.name')
                            ->label('Přihlášen v sérii')
                            ->placeholder('—')
                            ->helperText(fn (LessonAttendance $record): ?string => $record->isSubstituteGuest()
                                ? 'Klient sem chodí jako náhradu za svou vlastní sérii.'
                                : null)
                            ->url(fn (LessonAttendance $record): ?string => $record->enrollment?->series !== null
                                ? CourseSeriesResource::getUrl('view', ['record' => $record->enrollment->series])
                                : null),
                    ]),
                Section::make('Účast')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('presence')
                            ->label('Stav')
                            ->state(fn (LessonAttendance $record): string => AttendancePresenter::presenceLabel($record))
                            ->badge()
                            ->icon(fn (LessonAttendance $record): Heroicon => AttendancePresenter::presenceIcon($record))
                            ->color(fn (LessonAttendance $record): string => AttendancePresenter::presenceColor($record)),
                        TextEntry::make('excuse_reason')
                            ->label('Důvod omluvy')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('excusedBy.name')
                            ->label('Omluvil')
                            ->placeholder('—'),
                        TextEntry::make('cancelled_at')
                            ->label('Odhlášeno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('excuse_note')
                            ->label('Poznámka')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Náhrada')
                    ->columns(2)
                    ->visible(fn (LessonAttendance $record): bool => $record->isExcused()
                        || $record->replacement !== null
                        || $record->replacementFor !== null)
                    ->schema([
                        TextEntry::make('substitute')
                            ->label('Stav náhrady')
                            ->state(fn (LessonAttendance $record): ?string => AttendancePresenter::substituteLabel($record))
                            ->icon(fn (LessonAttendance $record): ?Heroicon => AttendancePresenter::substituteIcon($record))
                            ->color(fn (LessonAttendance $record): string => AttendancePresenter::substituteColor($record))
                            ->url(fn (LessonAttendance $record): ?string => AttendancePresenter::substituteUrl($record))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('replacementFor.lesson_id')
                            ->label('Náhrada za lekci')
                            ->state(fn (LessonAttendance $record): ?string => $record->replacementFor !== null
                                ? AttendancePresenter::lessonLabel($record->replacementFor->lesson)
                                : null)
                            ->placeholder('—')
                            ->url(fn (LessonAttendance $record): ?string => $record->replacementFor?->lesson !== null
                                ? LessonResource::getUrl('view', ['record' => $record->replacementFor->lesson])
                                : null),
                        TextEntry::make('replacement.lesson_id')
                            ->label('Nahrazeno lekcí')
                            ->state(fn (LessonAttendance $record): ?string => $record->replacement !== null
                                ? AttendancePresenter::lessonLabel($record->replacement->lesson)
                                : null)
                            ->placeholder('—')
                            ->url(fn (LessonAttendance $record): ?string => $record->replacement?->lesson !== null
                                ? LessonResource::getUrl('view', ['record' => $record->replacement->lesson])
                                : null),
                        IconEntry::make('token_generated')
                            ->label('Poukaz vydán')
                            ->boolean(),
                        TextEntry::make('substituteToken.expires_at')
                            ->label('Poukaz platí do')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('substituteToken.used_at')
                            ->label('Poukaz uplatněn')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
