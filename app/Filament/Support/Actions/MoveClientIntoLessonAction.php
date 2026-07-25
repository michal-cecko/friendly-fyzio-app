<?php

namespace App\Filament\Support\Actions;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonAttendance;
use App\Models\User;
use App\Notifications\SubstituteTokenNotification;
use App\Support\Emails\SentEmailReceipt;
use App\Support\Substitutes\MoveClientToLesson;
use App\Support\Substitutes\SubstituteException;
use App\Support\Substitutes\SubstituteOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Manual substitute override: move a client into this lesson, excusing them from
 * one of their own upcoming lessons in the same step. Deliberately bypasses the
 * automatic substitution rules (série pairing, token limits, capacity) — the
 * staff escape hatch behind {@see MoveClientToLesson}. Placed as a header action
 * on a lesson's attendance (Docházka) relation manager, whose owner record is the
 * target {@see CourseLesson}.
 */
class MoveClientIntoLessonAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'moveClientIntoLesson';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Přesunout klienta do lekce')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('primary')
            ->modalHeading('Přesunout klienta do lekce')
            ->modalDescription('Klienta odhlásí z vybrané vlastní lekce a přidá jej do této lekce — i mimo běžná pravidla náhrad (párování sérií, limity, kapacitu).')
            ->modalSubmitActionLabel('Přesunout')
            ->visible(fn (RelationManager $livewire): bool => self::target($livewire)?->endsAt()->isFuture() ?? false)
            ->schema([
                Select::make('client_id')
                    ->label('Klient')
                    ->required()
                    ->searchable()
                    ->live()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->whereHas('courseEnrollments', fn (Builder $query) => $query->where('status', CourseEnrollmentStatus::Active))
                        ->where(fn (Builder $query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (?string $value): ?string => User::find($value)?->name),
                Select::make('source_lesson_id')
                    ->label('Odhlásit z lekce')
                    ->helperText('Vlastní nadcházející lekce klienta, ze které bude odhlášen.')
                    ->required()
                    ->options(fn (Get $get, RelationManager $livewire): array => self::sourceLessonOptions($get('client_id'), self::target($livewire)))
                    ->visible(fn (Get $get): bool => filled($get('client_id')))
                    ->native(false),
                Placeholder::make('free_spots')
                    ->label('Volná místa v cílové lekci')
                    ->content(fn (RelationManager $livewire): string => self::freeSpotsLabel(self::target($livewire))),
                Toggle::make('notify_client')
                    ->label('Upozornit klienta?')
                    ->helperText('Odešle klientovi e-mail o rezervaci náhradní lekce.')
                    ->default(true),
            ])
            ->action(function (array $data, RelationManager $livewire): void {
                $target = self::target($livewire);
                $client = User::find($data['client_id']);
                $source = CourseLesson::find($data['source_lesson_id']);

                if ($target === null || $client === null || $source === null) {
                    Notification::make()->title('Přesun se nezdařil — chybí údaje.')->danger()->send();

                    return;
                }

                try {
                    app(MoveClientToLesson::class)($client, $target, $source);
                } catch (SubstituteException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                if ($data['notify_client'] ?? false) {
                    $client->notify(new SubstituteTokenNotification(
                        EmailTemplateKey::SubstituteManualMove,
                        [
                            'jmeno' => str((string) $client->name)->before(' ')->toString(),
                            'puvodni_kurz' => (string) ($source->series?->course?->name ?? $source->series?->name ?? ''),
                            'puvodni_lekce' => $source->startsAt()->format('j. n. Y · H:i'),
                            'kurz' => (string) ($target->series?->course?->name ?? $target->series?->name ?? ''),
                            'nova_lekce' => $target->startsAt()->format('j. n. Y · H:i'),
                            'misto' => (string) ($target->room?->name ?? ''),
                        ],
                    ));

                    SentEmailReceipt::forCurrentUser('Přesun do lekce');
                }

                Notification::make()->title('Klient byl přesunut do lekce.')->success()->send();
            });
    }

    protected static function target(RelationManager $livewire): ?CourseLesson
    {
        $owner = $livewire->getOwnerRecord();

        return $owner instanceof CourseLesson ? $owner : null;
    }

    /**
     * The client's own upcoming, not-yet-excused lessons, as [id => label]. These
     * are the lessons they can be pulled out of; the target lesson is excluded.
     *
     * @return array<string, string>
     */
    protected static function sourceLessonOptions(?string $clientId, ?CourseLesson $target): array
    {
        if ($clientId === null || $target === null) {
            return [];
        }

        $seriesIds = CourseEnrollment::query()
            ->where('client_id', $clientId)
            ->where('status', CourseEnrollmentStatus::Active)
            ->pluck('series_id');

        if ($seriesIds->isEmpty()) {
            return [];
        }

        $excusedLessonIds = LessonAttendance::query()
            ->whereNotNull('cancelled_at')
            ->whereHas('enrollment', fn (Builder $query) => $query->where('client_id', $clientId))
            ->pluck('lesson_id');

        return CourseLesson::query()
            ->whereIn('series_id', $seriesIds)
            ->whereKeyNot($target->getKey())
            ->whereNotIn('id', $excusedLessonIds)
            ->whereDate('lesson_date', '>=', today())
            ->with(['series.course', 'room'])
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (CourseLesson $lesson): bool => $lesson->endsAt()->isFuture())
            ->mapWithKeys(fn (CourseLesson $lesson): array => [
                $lesson->getKey() => trim(
                    ($lesson->series?->course?->name ?? $lesson->series?->name ?? 'Lekce')
                    .' · '.$lesson->startsAt()->format('j. n. Y · H:i'),
                    ' ·',
                ),
            ])
            ->all();
    }

    protected static function freeSpotsLabel(?CourseLesson $target): string
    {
        if ($target === null) {
            return '—';
        }

        $free = app(SubstituteOptions::class)->freeSpots($target);

        return $free > 0
            ? (string) $free
            : 'Lekce je plná — klient bude přidán nad kapacitu.';
    }
}
