<?php

namespace App\Filament\Support\Actions;

use App\Enums\EmailTemplateKey;
use App\Enums\LessonExcuseReason;
use App\Filament\Support\AttendancePresenter;
use App\Models\LessonAttendance;
use App\Models\SubstituteRule;
use App\Notifications\SubstituteTokenNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Substitutes\ExcuseFromLesson;
use App\Support\Substitutes\RestoreLessonAttendance;
use App\Support\Substitutes\SubstituteException;
use App\Support\Substitutes\SubstituteOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The presence switch on a lesson's Docházka tab. Everybody on the list is
 * present — that is what being on it means — until somebody says otherwise, so
 * this action only ever records the exception and takes it back. Before the
 * lesson that frees the spot for somebody else; afterwards it corrects the
 * register.
 *
 * Both directions take something away or give it back — a spot, a náhrada — so
 * both ask first, and both are written to the activity log against the seat they
 * changed.
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
            ->label(fn (LessonAttendance $record): string => self::isPresent($record)
                ? (self::hasHappened($record) ? 'Označit jako nepřítomného' : 'Odhlásit z lekce')
                : (self::hasHappened($record) ? 'Označit jako přítomného' : 'Vrátit do lekce'))
            ->icon(fn (LessonAttendance $record): Heroicon => self::isPresent($record)
                ? Heroicon::OutlinedUserMinus
                : Heroicon::OutlinedUserPlus)
            ->color(fn (LessonAttendance $record): string => self::isPresent($record) ? 'danger' : 'success')
            // A seat left behind by a cancelled sign-up holds no place, so there
            // is nothing to free or reclaim on it.
            ->disabled(fn (LessonAttendance $record): bool => AttendancePresenter::isCancelled($record))
            ->modal()
            ->modalHeading(fn (LessonAttendance $record): string => self::isPresent($record)
                ? (self::hasHappened($record) ? 'Označit klienta jako nepřítomného?' : 'Odhlásit klienta z lekce?')
                : 'Vrátit klienta do lekce?')
            ->modalDescription(fn (LessonAttendance $record): string => self::description($record))
            ->modalSubmitActionLabel(fn (LessonAttendance $record): string => self::isPresent($record)
                ? 'Odhlásit'
                : 'Vrátit')
            ->schema(fn (LessonAttendance $record): array => match (true) {
                ! self::isPresent($record) => [],
                self::followsCourseRules($record) => self::excuseFields($record),
                $record->isDropIn() => self::dropInFields(),
                default => [],
            })
            ->action(function (LessonAttendance $record, array $data): void {
                try {
                    self::isPresent($record)
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

    /**
     * Which way the switch is pointing. Being on the list is being present; only
     * an excuse points it the other way.
     */
    private static function isPresent(LessonAttendance $record): bool
    {
        return $record->cancelled_at === null;
    }

    /**
     * Whether the course's excuse rules apply to this seat at all. They do not
     * for a substitute sitting in from another run, and they do not for somebody
     * who bought this one lesson — a drop-in who cancels gets their money back,
     * not a náhrada poukaz.
     */
    private static function followsCourseRules(LessonAttendance $record): bool
    {
        return $record->enrollment !== null && ! $record->isSubstituteGuest();
    }

    private static function hasHappened(LessonAttendance $record): bool
    {
        return $record->lesson?->startsAt()->isPast() ?? false;
    }

    private static function description(LessonAttendance $record): string
    {
        if (self::isPresent($record)) {
            return self::hasHappened($record)
                ? 'Zaznamenáme, že klient na lekci nebyl.'
                : 'Místo se uvolní a bude ho možné nabídnout někomu dalšímu.';
        }

        return app(RestoreLessonAttendance::class)->wouldWithdrawToken($record)
            ? 'Klient bude na lekci opět počítán a náhrada za tuto lekci mu bude odebrána.'
            : 'Klient bude na lekci opět počítán a jeho místo se znovu obsadí.';
    }

    /**
     * @return array<int, Select|Textarea|Toggle>
     */
    private static function excuseFields(LessonAttendance $record): array
    {
        return [
            Select::make('excuse_reason')
                ->label('Důvod')
                ->options(LessonExcuseReason::class)
                ->native(false)
                ->placeholder('Neuvedeno'),
            Textarea::make('excuse_note')
                ->label('Poznámka')
                ->rows(2)
                ->maxLength(1000)
                ->helperText('Nepovinné, uvidí jen personál.'),
            Toggle::make('generate_substitute')
                ->label('Nabídnout klientovi náhradu')
                ->default(self::substituteBlockReason($record) === null && self::isEntitled($record))
                ->disabled(self::substituteBlockReason($record) !== null)
                ->helperText(self::substituteHelperText($record)),
            Toggle::make('notify_client')
                ->label('Informovat klienta e-mailem')
                ->default(true)
                ->helperText('Pošleme mu zprávu o zrušené lekci, s náhradou i bez ní.'),
        ];
    }

    /**
     * A drop-in seat — somebody who bought this one lesson — has no course rules
     * and no náhrada to weigh, so the only choice is whether to let them know.
     *
     * @return array<int, Toggle>
     */
    private static function dropInFields(): array
    {
        return [
            Toggle::make('notify_client')
                ->label('Informovat klienta e-mailem')
                ->default(true)
                ->helperText('Pošleme mu zprávu o zrušené lekci.'),
        ];
    }

    private static function isEntitled(LessonAttendance $record): bool
    {
        return $record->enrollment !== null && $record->lesson !== null
            && app(ExcuseFromLesson::class)->wouldGenerateToken($record->enrollment, $record->lesson);
    }

    /**
     * Why a poukaz cannot be issued at all, or null when it can. These two are
     * configuration, not a per-client judgement call, so there is no overriding
     * them from here: a série with no make-up allowance has them switched off,
     * and a série with no target séries has nowhere to redeem one — the poukaz
     * would arrive as an empty list in the client zone.
     */
    public static function substituteBlockReason(LessonAttendance $record): ?string
    {
        $enrollment = $record->enrollment;

        if ($enrollment === null) {
            return null;
        }

        if (app(ExcuseFromLesson::class)->substitutesAllowance($enrollment) < 1) {
            return 'Tato série náhrady nenabízí — „Max. náhrad“ je u ní nastavené na 0. Změňte to v nastavení série (záložka Náhrady), nebo klienta rovnou přesuňte přes „Přesunout klienta do lekce“.';
        }

        if (self::substituteTargets($record) === []) {
            return 'Tato série nemá nastavené náhradní série (záložka Náhrady) — klient by neměl poukaz kde uplatnit. Doplňte je, nebo ho rovnou přesuňte přes „Přesunout klienta do lekce“.';
        }

        return null;
    }

    /**
     * The séries a poukaz from this lesson may be redeemed in, exactly as
     * {@see SubstituteOptions::forToken()} resolves them.
     *
     * @return array<int, string>
     */
    public static function substituteTargets(LessonAttendance $record): array
    {
        if ($record->lesson?->series_id === null) {
            return [];
        }

        return SubstituteRule::query()
            ->where('source_series_id', $record->lesson->series_id)
            ->with('targetSeries.course')
            ->get()
            ->map(fn (SubstituteRule $rule): string => trim(
                ($rule->targetSeries?->course?->name ? $rule->targetSeries->course->name.' – ' : '')
                .($rule->targetSeries?->name ?? ''),
            ))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Says where the poukaz would be valid and what the rules allow — the série's
     * make-up limit and the course's cancellation deadline — without hiding the
     * override: staff may grant one past the deadline or past the limit, it just
     * has to be a deliberate choice.
     */
    public static function substituteHelperText(LessonAttendance $record): string
    {
        $enrollment = $record->enrollment;

        if ($enrollment === null) {
            return 'Vygeneruje poukaz na náhradní lekci v povoleném paralelním kurzu.';
        }

        if (($blocked = self::substituteBlockReason($record)) !== null) {
            return $blocked;
        }

        $excuse = app(ExcuseFromLesson::class);
        $allowance = $excuse->substitutesAllowance($enrollment);
        $remaining = $excuse->substitutesRemaining($enrollment);
        $where = 'Klient si vybere volný termín v: '.implode(', ', self::substituteTargets($record)).'.';

        if ($remaining < 1) {
            return "{$where} Limit náhrad je vyčerpaný ({$allowance} z {$allowance}) — vydáním další ho překročíte.";
        }

        if (! self::isEntitled($record)) {
            return "{$where} Pozdní odhlášení — podle pravidel kurzu nárok nevzniká, ale náhradu udělit můžete. Zbývá {$remaining} z {$allowance}.";
        }

        return "{$where} Zbývá {$remaining} z {$allowance}.";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function excuse(LessonAttendance $record, array $data): void
    {
        $lesson = $record->lesson;
        $reason = self::reason($data);
        $note = self::note($data);
        $wantsToken = (bool) ($data['generate_substitute'] ?? false)
            && self::substituteBlockReason($record) === null;
        $override = $wantsToken && ! self::isEntitled($record);

        if (self::followsCourseRules($record) && $lesson !== null) {
            $token = app(ExcuseFromLesson::class)(
                $record->enrollment,
                $lesson,
                allowToken: $wantsToken,
                notifyClient: (bool) ($data['notify_client'] ?? false),
                reason: $reason,
                note: $note,
                actor: auth()->user(),
                allowPast: self::hasHappened($record),
                overrideRules: $override,
            );
        } else {
            $record->update([
                'attended' => false,
                'cancelled_at' => now(),
                'excuse_reason' => $reason,
                'excuse_note' => $note,
                'excused_by_id' => auth()->id(),
            ]);

            if ((bool) ($data['notify_client'] ?? false) && $record->isDropIn()) {
                self::notifyDropInCancellation($record);
            }

            $token = null;
        }

        LogActivity::record(
            event: 'lesson_absence',
            subject: $record,
            description: 'Klient odhlášen z lekce: '.self::clientName($record),
            properties: [
                'client' => self::clientName($record),
                'lesson_id' => $record->lesson_id,
                'reason' => $reason?->getLabel(),
                'note' => $note,
                'substitute_token' => $token?->getKey(),
                'override' => $override,
                'past' => self::hasHappened($record),
                'notified' => (bool) ($data['notify_client'] ?? false),
            ],
        );
    }

    private static function restore(LessonAttendance $record): void
    {
        // No enrollment behind the seat means no náhrada to withdraw and no
        // série capacity rules to re-check — just put them back on the list.
        if ($record->enrollment === null) {
            $record->update([
                'attended' => true,
                'cancelled_at' => null,
                'excuse_reason' => null,
                'excuse_note' => null,
                'excused_by_id' => null,
            ]);

            self::logRevert($record, false);

            return;
        }

        $withdrawsToken = app(RestoreLessonAttendance::class)->wouldWithdrawToken($record);

        app(RestoreLessonAttendance::class)($record);

        self::logRevert($record, $withdrawsToken);
    }

    private static function logRevert(LessonAttendance $record, bool $withdrawsToken): void
    {
        LogActivity::record(
            event: 'lesson_absence_reverted',
            subject: $record,
            description: 'Klient vrácen do lekce: '.self::clientName($record),
            properties: [
                'client' => self::clientName($record),
                'lesson_id' => $record->lesson_id,
                'substitute_token_withdrawn' => $withdrawsToken,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function reason(array $data): ?LessonExcuseReason
    {
        $reason = $data['excuse_reason'] ?? null;

        return $reason instanceof LessonExcuseReason
            ? $reason
            : LessonExcuseReason::tryFrom((string) $reason);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function note(array $data): ?string
    {
        $note = trim((string) ($data['excuse_note'] ?? ''));

        return $note === '' ? null : $note;
    }

    private static function clientName(LessonAttendance $record): string
    {
        return $record->client?->name ?? 'neznámý klient';
    }

    /**
     * A drop-in buyer follows no course rules, so unenrolling them mints no
     * poukaz and sends none of the substitute-engine mail. When staff ask, they
     * still get a plain "your seat is cancelled" note — its own template, framed
     * for a single lesson rather than a course run.
     */
    private static function notifyDropInCancellation(LessonAttendance $record): void
    {
        $lesson = $record->lesson;

        $record->client?->notify(new SubstituteTokenNotification(
            EmailTemplateKey::LessonBookingCancelled,
            [
                'jmeno' => str((string) $record->client?->name)->before(' ')->toString(),
                'nazev' => (string) ($lesson?->displayName() ?? ''),
                'termin' => $lesson?->startsAt()->format('j. n. Y · H:i') ?? '',
            ],
        ));
    }
}
