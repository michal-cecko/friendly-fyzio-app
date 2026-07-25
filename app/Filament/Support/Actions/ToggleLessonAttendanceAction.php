<?php

namespace App\Filament\Support\Actions;

use App\Models\LessonAttendance;
use App\Support\ActivityLog\LogActivity;
use App\Support\Substitutes\ExcuseFromLesson;
use App\Support\Substitutes\RestoreLessonAttendance;
use App\Support\Substitutes\SubstituteException;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The "Zúčastní se?" switch on an upcoming lesson's presence list. Marking
 * somebody as not coming frees their spot straight away, so staff can offer it to
 * a substitute — and marking them back puts it right back.
 *
 * Neither direction happens silently: both ask first (an excuse can mint a
 * substitute token and e-mail the client, undoing it withdraws that token), and
 * both are written to the activity log.
 */
class ToggleLessonAttendanceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'toggleAttendance';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (LessonAttendance $record): string => self::isAttending($record)
                ? 'Odhlásit z lekce'
                : 'Vrátit do lekce')
            ->icon(fn (LessonAttendance $record): Heroicon => self::isAttending($record)
                ? Heroicon::OutlinedUserMinus
                : Heroicon::OutlinedUserPlus)
            ->color(fn (LessonAttendance $record): string => self::isAttending($record) ? 'danger' : 'success')
            ->modalHeading(fn (LessonAttendance $record): string => self::isAttending($record)
                ? 'Odhlásit klienta z lekce?'
                : 'Vrátit klienta do lekce?')
            ->modalDescription(fn (LessonAttendance $record): string => self::description($record))
            ->modalSubmitActionLabel(fn (LessonAttendance $record): string => self::isAttending($record)
                ? 'Odhlásit'
                : 'Vrátit')
            ->schema(fn (LessonAttendance $record): array => self::isAttending($record) && self::isOwnSeries($record)
                ? self::excuseFields($record)
                : [])
            ->action(function (LessonAttendance $record, array $data): void {
                try {
                    self::isAttending($record)
                        ? self::excuse($record, $data)
                        : self::restore($record);
                } catch (SubstituteException $exception) {
                    Notification::make()
                        ->title('Změnu se nepodařilo provést.')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Docházka byla upravena.')
                    ->success()
                    ->send();
            });
    }

    private static function isAttending(LessonAttendance $record): bool
    {
        return $record->cancelled_at === null;
    }

    /**
     * Substitutes sit in the lesson on an enrollment from another série, so the
     * course's own excuse rules (and its make-up entitlement) don't apply to them.
     */
    private static function isOwnSeries(LessonAttendance $record): bool
    {
        return $record->enrollment?->series_id === $record->lesson?->series_id;
    }

    private static function description(LessonAttendance $record): string
    {
        if (self::isAttending($record)) {
            return 'Místo se uvolní a bude ho možné nabídnout někomu dalšímu.';
        }

        return app(RestoreLessonAttendance::class)->wouldWithdrawToken($record)
            ? 'Klient bude na lekci opět počítán a náhrada za tuto lekci mu bude odebrána.'
            : 'Klient bude na lekci opět počítán a jeho místo se znovu obsadí.';
    }

    /**
     * @return array<int, Toggle>
     */
    private static function excuseFields(LessonAttendance $record): array
    {
        $entitled = $record->enrollment !== null && $record->lesson !== null
            && app(ExcuseFromLesson::class)->wouldGenerateToken($record->enrollment, $record->lesson);

        return [
            Toggle::make('generate_substitute')
                ->label('Nabídnout klientovi náhradu')
                ->default($entitled)
                ->disabled(! $entitled)
                ->helperText($entitled
                    ? 'Vygeneruje poukaz na náhradní lekci v povoleném paralelním kurzu.'
                    : 'Podle pravidel kurzu už na náhradu nárok nemá (pozdní odhlášení nebo vyčerpaný limit).'),
            Toggle::make('notify_client')
                ->label('Informovat klienta e-mailem')
                ->default(true)
                ->helperText('Odešle se jen společně s náhradou.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function excuse(LessonAttendance $record, array $data): void
    {
        $lesson = $record->lesson;

        if (self::isOwnSeries($record) && $record->enrollment !== null && $lesson !== null) {
            $token = app(ExcuseFromLesson::class)(
                $record->enrollment,
                $lesson,
                allowToken: (bool) ($data['generate_substitute'] ?? false),
                notifyClient: (bool) ($data['notify_client'] ?? false),
            );
        } else {
            $record->update(['attended' => false, 'cancelled_at' => now()]);
            $token = null;
        }

        LogActivity::record(
            event: 'lesson_absence',
            subject: $lesson,
            description: 'Klient odhlášen z lekce: '.self::clientName($record),
            properties: [
                'client' => self::clientName($record),
                'substitute_token' => $token?->getKey(),
                'notified' => (bool) ($data['notify_client'] ?? false) && $token !== null,
            ],
        );
    }

    private static function restore(LessonAttendance $record): void
    {
        $withdrawsToken = app(RestoreLessonAttendance::class)->wouldWithdrawToken($record);

        app(RestoreLessonAttendance::class)($record);

        LogActivity::record(
            event: 'lesson_absence_reverted',
            subject: $record->lesson,
            description: 'Klient vrácen do lekce: '.self::clientName($record),
            properties: [
                'client' => self::clientName($record),
                'substitute_token_withdrawn' => $withdrawsToken,
            ],
        );
    }

    private static function clientName(LessonAttendance $record): string
    {
        return $record->enrollment?->client?->name ?? 'neznámý klient';
    }
}
