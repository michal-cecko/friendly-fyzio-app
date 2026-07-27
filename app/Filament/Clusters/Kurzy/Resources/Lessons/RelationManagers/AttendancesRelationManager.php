<?php

namespace App\Filament\Clusters\Kurzy\Resources\Lessons\RelationManagers;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\AddParticipantAction;
use App\Filament\Support\Actions\EditExcuseAction;
use App\Filament\Support\Actions\MoveClientIntoLessonAction;
use App\Filament\Support\Actions\ToggleLessonAttendanceAction;
use App\Filament\Support\AttendancePresenter;
use App\Filament\Support\Tables\RecordLinkColumn;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\User;
use App\Support\Enrollments\LessonRoster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Everybody who will be in the room for this lesson, shown as a section on the
 * lesson detail page — one row per client, whichever way they got their seat:
 * enrolled in the série ({@see LessonRoster}), moved in from another run
 * ({@see MoveClientIntoLessonAction}), or bought this single lesson.
 *
 * Being on the list is being present. The switch in the Účast column is there
 * for the exception, and it goes through {@see ToggleLessonAttendanceAction},
 * which asks before it frees a spot or takes a náhrada back.
 *
 * Seats left behind by a cancelled sign-up are kept for history but hidden by
 * default — they no longer hold a place, and a list of who is coming should not
 * name them.
 *
 * The Náhrada column closes the loop the substitute engine opens: what the missed
 * lesson was swapped for, or whose missed lesson this row is making up.
 */
class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Docházka';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCheckCircle;

    public function isReadOnly(): bool
    {
        return false;
    }

    private function lesson(): ?Lesson
    {
        $lesson = $this->getOwnerRecord();

        return $lesson instanceof Lesson ? $lesson : null;
    }

    private function lessonHasHappened(): bool
    {
        return $this->lesson()?->startsAt()->isPast() ?? false;
    }

    public function table(Table $table): Table
    {
        $lesson = $this->lesson();
        $happened = $this->lessonHasHappened();
        $partOfSeries = $lesson?->series_id !== null;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'client',
                    'enrollment.series',
                    'booking',
                    'excusedBy',
                    'lesson.series',
                    'replacement.lesson.series',
                    'replacementFor.lesson.series',
                    'substituteToken',
                ])
                ->when($partOfSeries, fn (Builder $seats): Builder => $seats->addSelect([
                    'series_held_count' => $this->seriesTally($lesson),
                    'series_attended_count' => $this->seriesTally($lesson)->where('tally.attended', true),
                ])))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'lesson_attendances.client_id'),
            ))
            ->headerActions([
                // Only for a lesson that is actually on sale as a single seat —
                // this books one, money and confirmation e-mail included. A
                // course client belongs to their série, so they come in through
                // the move action instead.
                AddParticipantAction::make()
                    ->visible(fn (): bool => $lesson?->offerState()->acceptsRegistrations() ?? false),
                MoveClientIntoLessonAction::make(),
            ])
            ->columns([
                RecordLinkColumn::make('client.name', fn (LessonAttendance $record): ?User => $record->client)
                    ->label('Klient')
                    ->searchable(),
                // The sign-up behind the seat. The ordinary case — a member of
                // this lesson's own série — is just the série's name in plain
                // text; there is nothing to flag about it. A badge is what the
                // exceptions get.
                TextColumn::make('origin')
                    ->label('Přihláška')
                    ->state(fn (LessonAttendance $record): ?string => self::seatLabel($record))
                    ->badge(fn (LessonAttendance $record): bool => ! self::isOrdinarySeat($record))
                    ->color(fn (LessonAttendance $record): ?string => self::isOrdinarySeat($record)
                        ? null
                        : AttendancePresenter::originColor($record))
                    ->url(fn (LessonAttendance $record): ?string => AttendancePresenter::seatUrl($record))
                    ->placeholder('—'),
                IconColumn::make('presence')
                    ->label($happened ? 'Účast' : 'Zúčastní se?')
                    ->state(fn (LessonAttendance $record): bool => AttendancePresenter::isPresent($record))
                    ->icon(fn (LessonAttendance $record): Heroicon => AttendancePresenter::presenceIcon($record))
                    ->color(fn (LessonAttendance $record): string => AttendancePresenter::presenceColor($record))
                    ->tooltip(fn (LessonAttendance $record): string => self::presenceTooltip($record, $happened))
                    ->action(ToggleLessonAttendanceAction::make()),
                TextColumn::make('substitute')
                    ->label('Náhrada')
                    ->state(fn (LessonAttendance $record): ?string => AttendancePresenter::substituteLabel($record))
                    ->icon(fn (LessonAttendance $record): ?Heroicon => AttendancePresenter::substituteIcon($record))
                    ->color(fn (LessonAttendance $record): string => AttendancePresenter::substituteColor($record))
                    ->url(fn (LessonAttendance $record): ?string => AttendancePresenter::substituteUrl($record))
                    ->placeholder('—'),
                TextColumn::make('excuse_reason')
                    ->label('Důvod')
                    ->badge()
                    ->tooltip(fn (LessonAttendance $record): ?string => $record->excuse_note)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client.phone')
                    ->label('Telefon')
                    ->icon(Heroicon::OutlinedPhone)
                    ->copyable()
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client.email')
                    ->label('E-mail')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->copyable()
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('series_tally')
                    ->label('Docházka v sérii')
                    ->state(fn (LessonAttendance $record): ?string => self::tallyLabel($record))
                    ->tooltip('Kolika z dosud proběhlých lekcí této série se klient zúčastnil.')
                    ->placeholder('—')
                    ->visible($partOfSeries)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('excusedBy.name')
                    ->label('Omluvil')
                    ->description(fn (LessonAttendance $record): ?string => $record->cancelled_at?->format('d.m.Y H:i'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('excuse_note')
                    ->label('Poznámka')
                    ->limit(40)
                    ->tooltip(fn (LessonAttendance $record): ?string => $record->excuse_note)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('presence')
                    ->label($happened ? 'Účast' : 'Zúčastní se?')
                    ->options(['present' => 'Přítomní', 'absent' => 'Nepřítomní'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'present' => $query->whereNull('cancelled_at'),
                        'absent' => $query->whereNotNull('cancelled_at'),
                        default => $query,
                    }),
                SelectFilter::make('origin')
                    ->label('Přihláška')
                    ->options([
                        'course' => 'Kurz',
                        'substitute' => 'Náhrada',
                        'drop_in' => 'Jednorázový vstup',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->filterByOrigin($query, $data['value'] ?? null)),
                // Rows of a sign-up that has since been cancelled are history,
                // not a guest list. They are kept — an excuse of theirs still
                // happened — but the list hides them until asked. Switching the
                // filter off is how you ask.
                Filter::make('hide_cancelled')
                    ->label('Skrýt zrušené přihlášky')
                    ->default()
                    ->query(fn (Builder $query): Builder => $this->hideCancelledSeats($query)),
            ])
            ->recordActions([
                EditExcuseAction::make(),
                ActivityLogAction::make(),
                Action::make('detail')
                    ->label('Detail')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (LessonAttendance $record): string => LessonAttendanceResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Zatím nikdo')
            ->emptyStateDescription('Účastníci se objeví po přihlášení do série nebo po koupi jednorázového vstupu.');
    }

    /**
     * A seat held by somebody enrolled in this lesson's own série — the reason
     * nearly everybody in the room is there, and so the row that needs no label
     * beyond which run it is.
     */
    private static function isOrdinarySeat(LessonAttendance $record): bool
    {
        return ! AttendancePresenter::isCancelled($record)
            && ! $record->isDropIn()
            && ! $record->isSubstituteGuest();
    }

    /**
     * What the sign-up is, named as briefly as it can be: the série for a member
     * of it, the série they are making up for a guest, and for the two rows that
     * belong to no série at all, what they are instead.
     */
    private static function seatLabel(LessonAttendance $record): ?string
    {
        if (AttendancePresenter::isCancelled($record) || $record->isDropIn()) {
            return AttendancePresenter::originLabel($record);
        }

        $series = $record->enrollment?->series?->name;

        if ($record->isSubstituteGuest()) {
            return 'Náhrada · '.($series ?? 'jiná série');
        }

        return $series;
    }

    /**
     * Drops seats whose sign-up was cancelled — by staff, or by the unpaid-hold
     * sweep. Excused rows are a different thing entirely and always stay: those
     * people are still participants, they just are not coming this time.
     */
    private function hideCancelledSeats(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $seats): Builder => $seats
                ->whereNull('enrollment_id')
                ->orWhereHas('enrollment', fn (Builder $enrollment): Builder => $enrollment
                    ->where('status', '!=', CourseEnrollmentStatus::Cancelled)))
            ->where(fn (Builder $seats): Builder => $seats
                ->whereNull('booking_id')
                ->orWhereHas('booking', fn (Builder $booking): Builder => $booking
                    ->whereIn('status', BookingStatus::occupying())));
    }

    private function filterByOrigin(Builder $query, ?string $origin): Builder
    {
        $seriesId = $this->lesson()?->series_id;

        return match ($origin) {
            'drop_in' => $query->whereNotNull('booking_id'),
            'course' => $query->whereNull('booking_id')
                ->whereHas('enrollment', fn (Builder $enrollment): Builder => $enrollment->where('series_id', $seriesId)),
            'substitute' => $query->whereNull('booking_id')
                ->whereHas('enrollment', fn (Builder $enrollment): Builder => $enrollment->where('series_id', '!=', $seriesId)),
            default => $query,
        };
    }

    /**
     * How many of this série's lessons the row's client has sat through so far,
     * as a correlated subquery — the alternative is a COUNT per row. Only
     * lessons that have already been held count; the ones still to come are not
     * a record of anything yet.
     */
    private function seriesTally(Lesson $lesson): \Illuminate\Database\Query\Builder
    {
        return DB::table('lesson_attendances as tally')
            ->selectRaw('count(*)')
            ->join('lessons as tally_lesson', 'tally_lesson.id', '=', 'tally.lesson_id')
            ->whereColumn('tally.client_id', 'lesson_attendances.client_id')
            ->where('tally_lesson.series_id', $lesson->series_id)
            ->whereNull('tally_lesson.deleted_at')
            ->whereDate('tally_lesson.lesson_date', '<=', today());
    }

    private static function tallyLabel(LessonAttendance $record): ?string
    {
        $held = $record->getAttribute('series_held_count');

        return $held > 0
            ? $record->getAttribute('series_attended_count').'/'.$held
            : null;
    }

    private static function presenceTooltip(LessonAttendance $record, bool $happened): string
    {
        if (! AttendancePresenter::isPresent($record)) {
            return 'Omluven, místo je volné — kliknutím ho vrátíte';
        }

        return $happened
            ? 'Dorazil — kliknutím ho označíte jako nepřítomného'
            : 'Počítáme s ním — kliknutím ho odhlásíte';
    }
}
