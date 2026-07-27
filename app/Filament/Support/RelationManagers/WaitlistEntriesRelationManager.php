<?php

namespace App\Filament\Support\RelationManagers;

use App\Filament\Support\Actions\SendEmailAction;
use App\Jobs\SendBulkParticipantEmailJob;
use App\Mason\Support\EmailFields;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\WaitlistEntry;
use App\Support\Emails\WaitlistEntryEmailer;
use App\Support\Enrollments\AlreadySignedUpException;
use App\Support\Enrollments\EnrollmentData;
use App\Support\Enrollments\EnrollmentEmailContext;
use App\Support\Enrollments\InviteSummary;
use App\Support\Enrollments\InviteWaitlistToSpot;
use App\Support\Enrollments\JoinWaitlist;
use App\Support\Enrollments\OfferClosedException;
use App\Support\Enrollments\OfferSpotToEntry;
use App\Support\Enrollments\PromoteFromWaitlist;
use App\Support\Enrollments\SignUpForOffer;
use App\Support\Reservations\DeactivatedClientException;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared waitlist tab for every enrollable offer (course series, one-time
 * lesson, workshop) and for courses themselves (there the entries are
 * "notify me when registration opens" interest sign-ups). Entries come from
 * the public site. When the offer has automatic promotion on, freed spots are
 * filled for you; with it off, staff work the list by hand: e-mail the people
 * on it, offer them a spot (reserve it, or run a "kdo dřív zaplatí" race), or
 * register them straight into a série.
 *
 * On a Course owner there is no single run to place someone into, so the invite
 * and register actions first ask which série of that course to use.
 */
class WaitlistEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'waitlistEntries';

    protected static ?string $title = 'Čekací listina';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedQueueList;

    /**
     * On a course the entries are "notify me when a new série opens" interest
     * sign-ups, so the tab is titled accordingly; on the enrollable offers
     * (série, one-time lesson, workshop) it is the real waitlist.
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return $ownerRecord instanceof Course ? 'Chci vědět první' : 'Čekací listina';
    }

    /**
     * The list is worked from the offer's View page, where Filament would
     * otherwise treat every relation manager as read-only — which silently hides
     * the built-in delete actions while leaving the custom ones visible.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->defaultSort('created_at')
            ->modelLabel('zájemce')
            ->pluralModelLabel('zájemce')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Přihlášen')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Jméno')
                    ->state(fn (WaitlistEntry $record): string => $record->displayName())
                    ->description(fn (WaitlistEntry $record): ?string => $record->client !== null ? 'Registrovaný klient' : 'Bez účtu'),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->state(fn (WaitlistEntry $record): ?string => $record->displayEmail())
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->state(fn (WaitlistEntry $record): ?string => $record->displayPhone())
                    ->placeholder('—'),
                TextColumn::make('notified_at')
                    ->label('Upozorněn')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Čeká'),
                TextColumn::make('confirmed_at')
                    ->label('Potvrzeno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('addEntry')
                    ->label('Přidat zájemce')
                    ->icon(Heroicon::OutlinedPlus)
                    ->color('gray')
                    ->modalHeading(fn (): string => $this->getOwnerRecord() instanceof Course
                        ? 'Přidat zájemce o kurz'
                        : 'Přidat na čekací listinu')
                    ->modalDescription('Pro někoho, kdo se ozval mimo web — telefonem, e-mailem, osobně. Pokud už účet s tímto e-mailem existuje, zájemce se k němu připojí.')
                    ->modalSubmitActionLabel('Přidat')
                    ->schema([
                        TextInput::make('name')
                            ->label('Jméno')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(255),
                        Toggle::make('notify')
                            ->label('Poslat potvrzení e-mailem')
                            ->helperText('Stejný e-mail, jaký chodí po přihlášení z webu — s pořadím na listině.')
                            ->default(true)
                            // A course's list is an interest sign-up that stays
                            // silent until a série opens, so there is nothing to
                            // send and nothing to decide.
                            ->visible(fn (): bool => ! $this->getOwnerRecord() instanceof Course),
                    ])
                    ->action(function (array $data): void {
                        $this->addEntry($data);
                    }),
                Action::make('promote')
                    ->label('Přidat z čekací listiny')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('primary')
                    ->visible(fn (): bool => $this->hasWaitingEntries())
                    ->disabled(fn (): bool => ! $this->canPromoteNow())
                    ->requiresConfirmation()
                    ->modalHeading('Přidat z čekací listiny')
                    ->modalDescription('Systém osloví dalšího v pořadí — vytvoří nezávaznou přihlášku a pošle výzvu k platbě. Přidá tolik lidí, kolik je právě volných míst.')
                    ->modalSubmitActionLabel('Přidat')
                    ->action(function (): void {
                        $offer = $this->promotableOffer();

                        if ($offer === null) {
                            return;
                        }

                        PromoteFromWaitlist::handle($offer);

                        Notification::make()
                            ->success()
                            ->title('Hotovo')
                            ->body('Oslovili jsme další v pořadí podle počtu volných míst.')
                            ->send();
                    }),
                Action::make('invite')
                    ->label('Oslovit čekající')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->visible(fn (): bool => $this->hasWaitingEntries())
                    ->disabled(fn (): bool => ! $this->canPromoteNow())
                    ->requiresConfirmation()
                    ->modalHeading('Oslovit čekající')
                    ->modalDescription(fn (): string => 'Všem čekajícím pošleme e-mail s přihlašovacím odkazem — kdo se první přihlásí, ten místo dostane. Po dobu '.Settings::waitlistInviteHours().' hodin ho z webu nikdo jiný neobsadí.')
                    ->modalSubmitActionLabel('Oslovit')
                    ->action(function (): void {
                        $offer = $this->promotableOffer();

                        if ($offer === null) {
                            return;
                        }

                        $invited = app(InviteWaitlistToSpot::class)->handle($offer);

                        Notification::make()
                            ->success()
                            ->title($invited > 0 ? 'Odesláno' : 'Nikoho jsme neoslovili')
                            ->body($invited > 0
                                ? "Oslovili jsme čekajících: {$invited}. Kdo se první přihlásí, ten místo dostane."
                                : 'Buď už nabídka běží, nebo na listině nikdo nečeká.')
                            ->send();
                    }),
            ])
            ->recordActions([
                SendEmailAction::make()
                    ->visible(fn (WaitlistEntry $record): bool => $record->isPending() && $record->emailRecipientAddress() !== null),
                Action::make('inviteToCourse')
                    ->label('Pozvat do kurzu')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('primary')
                    ->visible(fn (WaitlistEntry $record): bool => $this->canTargetOffer() && $record->isPending() && $record->displayEmail() !== null)
                    ->modalHeading('Pozvat do kurzu')
                    ->modalSubmitActionLabel('Pozvat')
                    ->schema(fn (): array => $this->inviteSchema())
                    ->action(function (WaitlistEntry $record, array $data): void {
                        $this->runInvite(collect([$record]), $data);
                    }),
                Action::make('registerToSeries')
                    ->label('Zapsat do série')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('gray')
                    ->visible(fn (WaitlistEntry $record): bool => $this->canTargetOffer() && $record->isPending() && $record->displayEmail() !== null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (WaitlistEntry $record): string => 'Zapsat '.$record->displayName().' do série')
                    ->modalDescription('Vytvoří přihlášku jako z veřejného webu: založí účet, vystaví výzvu k QR platbě a odešle potvrzovací e-mail.')
                    ->modalSubmitActionLabel('Zapsat')
                    ->schema(fn (): array => $this->seriesPickerSchema())
                    ->action(function (WaitlistEntry $record, array $data): void {
                        $this->runRegister(collect([$record]), $data);
                    }),
                DeleteAction::make()
                    ->label('Odebrat')
                    ->modalHeading(fn (WaitlistEntry $record): string => 'Odebrat '.$record->displayName().' z čekací listiny'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('sendWaitlistEmail')
                        ->label('Odeslat e-mail')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->color('gray')
                        ->modalHeading('Odeslat e-mail zájemcům')
                        ->modalSubmitActionLabel('Odeslat')
                        ->schema($this->waitlistEmailSchema())
                        ->action(fn (Collection $records, array $data) => $this->runBulkEmail($records, $data))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('inviteToCourse')
                        ->label('Pozvat do kurzu')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->color('primary')
                        ->visible(fn (): bool => $this->canTargetOffer())
                        ->modalHeading('Pozvat vybrané do kurzu')
                        ->modalSubmitActionLabel('Pozvat')
                        ->schema(fn (): array => $this->inviteSchema())
                        ->action(fn (Collection $records, array $data) => $this->runInvite($records->filter(fn (WaitlistEntry $e): bool => $e->isPending()), $data))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('registerToSeries')
                        ->label('Zapsat do série')
                        ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->color('gray')
                        ->visible(fn (): bool => $this->canTargetOffer())
                        ->requiresConfirmation()
                        ->modalHeading('Zapsat vybrané do série')
                        ->modalDescription('Ke každému vybranému založí přihlášku, vystaví výzvu k QR platbě a odešle potvrzovací e-mail.')
                        ->modalSubmitActionLabel('Zapsat')
                        ->schema(fn (): array => $this->seriesPickerSchema())
                        ->action(fn (Collection $records, array $data) => $this->runRegister($records->filter(fn (WaitlistEntry $e): bool => $e->isPending()), $data))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->label('Odebrat')
                        ->modalHeading('Odebrat vybrané z čekací listiny'),
                ]),
            ]);
    }

    /**
     * Adds somebody to the list by hand, through the same engine the public
     * forms use — so an existing account is linked by e-mail and a duplicate
     * simply returns the entry that is already waiting rather than queueing the
     * same person twice.
     *
     * @param  array{name?: string, email?: string, phone?: ?string, notify?: bool}  $data
     */
    protected function addEntry(array $data): void
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Course && ! $owner instanceof CourseSeries && ! $owner instanceof Lesson) {
            return;
        }

        $entry = JoinWaitlist::handle(
            $owner,
            $data['name'] ?? null,
            (string) ($data['email'] ?? ''),
            $data['phone'] ?? null,
            notify: (bool) ($data['notify'] ?? false),
        );

        if (! $entry->wasRecentlyCreated) {
            Notification::make()
                ->warning()
                ->title($entry->displayName().' už na listině je')
                ->body('Se stejným e-mailem tu čeká od '.$entry->created_at?->format('j. n. Y').'.')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Přidáno')
            ->body($entry->displayName().' je na listině.')
            ->send();
    }

    protected function promotableOffer(): CourseSeries|Lesson|null
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof CourseSeries || $owner instanceof Lesson
            ? $owner
            : null;
    }

    /**
     * Whether anybody is actually waiting. Both header actions work the pending
     * end of the list, so with nobody on it there is nothing to offer — the
     * buttons are hidden rather than shown greyed out.
     */
    protected function hasWaitingEntries(): bool
    {
        return $this->promotableOffer()?->waitlistEntries()->pending()->exists() ?? false;
    }

    /**
     * Somebody is waiting, but the run also has to have room — that case stays
     * visible and disabled, because it is worth seeing that the list is stuck on
     * capacity rather than empty.
     */
    protected function canPromoteNow(): bool
    {
        return $this->hasWaitingEntries()
            && ($this->promotableOffer()?->spotsLeft() ?? 0) > 0;
    }

    /**
     * Whether there is a série/event to invite or register people into — the
     * owner itself, or (for a Course) at least one of its séries.
     */
    protected function canTargetOffer(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof CourseSeries
            || $owner instanceof Lesson
            || ($owner instanceof Course && $owner->series()->exists());
    }

    /**
     * The offer to act on: the owner itself, or the série chosen in the modal
     * when the owner is a Course.
     */
    protected function resolveTargetOffer(array $data): CourseSeries|Lesson|null
    {
        $owner = $this->getOwnerRecord();

        if ($owner instanceof CourseSeries || $owner instanceof Lesson) {
            return $owner;
        }

        if ($owner instanceof Course && filled($data['series_id'] ?? null)) {
            return $owner->series()->whereKey($data['series_id'])->first();
        }

        return null;
    }

    /**
     * @return array<int, Select|ToggleButtons>
     */
    protected function inviteSchema(): array
    {
        return array_values(array_filter([
            $this->targetSeriesField(),
            ToggleButtons::make('mode')
                ->label('Způsob pozvání')
                ->options([
                    'hold' => 'Rezervovat místo',
                    'race' => 'Kdo dřív zaplatí',
                ])
                ->default('hold')
                ->inline()
                ->required(),
        ]));
    }

    /**
     * @return array<int, Select>
     */
    protected function seriesPickerSchema(): array
    {
        return array_values(array_filter([$this->targetSeriesField()]));
    }

    protected function targetSeriesField(): ?Select
    {
        if (! $this->getOwnerRecord() instanceof Course) {
            return null;
        }

        return Select::make('series_id')
            ->label('Série')
            ->required()
            ->searchable()
            ->options($this->seriesOptions());
    }

    /**
     * @return array<string, string>
     */
    protected function seriesOptions(): array
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Course) {
            return [];
        }

        return $owner->series()
            ->orderByDesc('start_date')
            ->get()
            ->mapWithKeys(function (CourseSeries $series): array {
                $period = EnrollmentEmailContext::seriesPeriod($series);

                return [$series->getKey() => trim($series->name.($period !== '' ? ' ('.$period.')' : ''))];
            })
            ->all();
    }

    /**
     * @return array<int, ToggleButtons|Select|TextInput>
     */
    protected function waitlistEmailSchema(): array
    {
        return [
            ToggleButtons::make('mode')
                ->label('Režim')
                ->options(['custom' => 'Vlastní e-mail', 'template' => 'Šablona'])
                ->default('custom')
                ->inline()
                ->live()
                ->required(),
            Select::make('template_key')
                ->label('Šablona e-mailu')
                ->required()
                ->options(WaitlistEntryEmailer::broadcastTemplateOptions())
                ->visible(fn (Get $get): bool => $get('mode') === 'template'),
            TextInput::make('subject')
                ->label('Předmět')
                ->required()
                ->visible(fn (Get $get): bool => $get('mode') === 'custom'),
            EmailFields::richText('body', 'Text e-mailu', required: true)
                ->visible(fn (Get $get): bool => $get('mode') === 'custom'),
        ];
    }

    /**
     * @param  Collection<int, WaitlistEntry>  $entries
     */
    protected function runInvite(Collection $entries, array $data): void
    {
        $offer = $this->resolveTargetOffer($data);

        if ($offer === null) {
            $this->notifyNoTarget();

            return;
        }

        $summary = app(OfferSpotToEntry::class)->inviteMany($offer, $entries, ($data['mode'] ?? 'hold') === 'hold');

        $this->notifyInviteSummary($summary);
    }

    /**
     * @param  Collection<int, WaitlistEntry>  $entries
     */
    protected function runRegister(Collection $entries, array $data): void
    {
        $offer = $this->resolveTargetOffer($data);

        if ($offer === null) {
            $this->notifyNoTarget();

            return;
        }

        $this->notifyRegisterResult($this->registerEntries($entries, $offer));
    }

    /**
     * @param  Collection<int, WaitlistEntry>  $records
     */
    protected function runBulkEmail(Collection $records, array $data): void
    {
        $ids = $records
            ->filter(fn (WaitlistEntry $entry): bool => $entry->isPending() && $entry->displayEmail() !== null)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            Notification::make()
                ->warning()
                ->title('Nikomu nešlo poslat e-mail.')
                ->body('Vybraní zájemci už byli osloveni nebo nemají e-mail.')
                ->send();

            return;
        }

        $isTemplate = ($data['mode'] ?? 'custom') === 'template';

        SendBulkParticipantEmailJob::dispatch(
            signupClass: WaitlistEntry::class,
            signupIds: $ids,
            templateKey: $isTemplate ? $data['template_key'] : null,
            subject: $isTemplate ? null : $data['subject'],
            bodyHtml: $isTemplate ? null : $data['body'],
            senderId: auth()->id(),
        );

        Notification::make()
            ->success()
            ->title('Odesílání spuštěno')
            ->body('Zpráva míří na '.count($ids).' příjemců. Až budou odeslány, dáme vám vědět.')
            ->send();
    }

    /**
     * @param  Collection<int, WaitlistEntry>  $entries
     * @return array{registered:int, full:int, duplicate:int, deactivated:int, noEmail:int}
     */
    protected function registerEntries(Collection $entries, CourseSeries|Lesson $offer): array
    {
        $result = ['registered' => 0, 'full' => 0, 'duplicate' => 0, 'deactivated' => 0, 'noEmail' => 0];
        $signUp = app(SignUpForOffer::class);

        foreach ($entries as $entry) {
            if (! $entry->isPending()) {
                continue;
            }

            if ($entry->displayEmail() === null) {
                $result['noEmail']++;

                continue;
            }

            $data = new EnrollmentData(
                name: (string) ($entry->displayName() ?: $entry->displayEmail()),
                email: (string) $entry->displayEmail(),
                phone: $entry->displayPhone(),
                note: null,
                client: $entry->client,
            );

            try {
                // Staff placing someone by hand bypass the public gating the same
                // way a hidden link does — an Inactive/Private run, or a spot
                // fenced off by a running waitlist invite round, must not stop
                // them. Real capacity is still enforced.
                $signup = $offer instanceof CourseSeries
                    ? $signUp->forSeries($offer, $data, viaPresale: true)
                    : $signUp->forEvent($offer, $data, viaPresale: true);
            } catch (OfferClosedException) {
                // Full or closed — the remaining entries would all fail too.
                $result['full']++;

                break;
            } catch (AlreadySignedUpException) {
                $result['duplicate']++;
                $entry->forceFill(['notified_at' => now()])->save();

                continue;
            } catch (DeactivatedClientException) {
                $result['deactivated']++;

                continue;
            }

            $entry->forceFill(['client_id' => $signup->client_id, 'notified_at' => now()])->save();
            $result['registered']++;
        }

        return $result;
    }

    protected function notifyInviteSummary(InviteSummary $summary): void
    {
        $body = $this->skippedBody([
            $summary->skippedFull > 0 ? $summary->skippedFull.' nad kapacitu' : null,
            $summary->skippedDeadEnd > 0 ? $summary->skippedDeadEnd.' nevyřízeno' : null,
            $summary->skippedNoEmail > 0 ? $summary->skippedNoEmail.' bez e-mailu' : null,
        ]);

        if ($summary->offered > 0) {
            Notification::make()
                ->success()
                ->title('Pozvánka odeslána ('.$summary->offered.')')
                ->body($body)
                ->send();

            return;
        }

        Notification::make()
            ->warning()
            ->title('Nikomu se nepodařilo nabídnout místo')
            ->body($body)
            ->send();
    }

    /**
     * @param  array{registered:int, full:int, duplicate:int, deactivated:int, noEmail:int}  $result
     */
    protected function notifyRegisterResult(array $result): void
    {
        $body = $this->skippedBody([
            $result['full'] > 0 ? 'kapacita naplněná' : null,
            $result['duplicate'] > 0 ? $result['duplicate'].' už přihlášeno' : null,
            $result['deactivated'] > 0 ? $result['deactivated'].' deaktivováno' : null,
            $result['noEmail'] > 0 ? $result['noEmail'].' bez e-mailu' : null,
        ]);

        if ($result['registered'] > 0) {
            Notification::make()
                ->success()
                ->title('Zapsáno do série ('.$result['registered'].')')
                ->body($body)
                ->send();

            return;
        }

        Notification::make()
            ->warning()
            ->title('Nikdo nebyl zapsán')
            ->body($body)
            ->send();
    }

    /**
     * @param  array<int, string|null>  $parts
     */
    protected function skippedBody(array $parts): ?string
    {
        $parts = array_values(array_filter($parts));

        return $parts === [] ? null : 'Přeskočeno: '.implode(', ', $parts).'.';
    }

    protected function notifyNoTarget(): void
    {
        Notification::make()
            ->warning()
            ->title('Vyberte prosím sérii.')
            ->send();
    }
}
