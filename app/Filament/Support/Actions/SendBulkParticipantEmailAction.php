<?php

namespace App\Filament\Support\Actions;

use App\Enums\EmailTemplateKey;
use App\Filament\Support\Schemas\CopyRecipientsFields;
use App\Jobs\SendBulkParticipantEmailJob;
use App\Mason\Support\EmailFields;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Support\Emails\CopyRecipients;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Writes to everyone signed up for one offer — a course série or a one-off
 * event — either as a free-form message or as one of the few CMS templates that
 * still make sense addressed to a whole group.
 *
 * Sending happens on the queue ({@see SendBulkParticipantEmailJob}): a full
 * série is dozens of rendered e-mails, and the admin gets the final count as a
 * database notification rather than waiting on the request.
 */
class SendBulkParticipantEmailAction extends Action
{
    /**
     * The only templates worth addressing to a whole group. Everything else in
     * the enrollment set is a per-person receipt (sign-up confirmations,
     * waitlist notices, individual cancellations) and would read as nonsense in
     * bulk.
     *
     * @var list<EmailTemplateKey>
     */
    private const BROADCAST_KEYS = [
        EmailTemplateKey::EnrollmentCancelledByClinic,
        EmailTemplateKey::LessonScheduleChanged,
    ];

    public static function getDefaultName(): ?string
    {
        return 'emailParticipants';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Napsat účastníkům')
            ->icon(Heroicon::OutlinedEnvelopeOpen)
            ->color('gray')
            ->visible(fn (CourseSeries|OneOffEvent $record): bool => static::participants($record)->isNotEmpty())
            ->modalHeading('Napsat účastníkům')
            ->modalDescription(fn (CourseSeries|OneOffEvent $record): string => 'Přihlášeno '
                .static::participants($record)->count()
                .' – vyberte, komu zpráva půjde.')
            ->modalSubmitActionLabel('Odeslat')
            ->schema([
                ToggleButtons::make('audience')
                    ->label('Komu')
                    ->options(fn (CourseSeries|OneOffEvent $record): array => [
                        'all' => 'Všem přihlášeným ('.static::participants($record)->count().')',
                        'selected' => 'Vybraným',
                    ])
                    ->default('all')
                    ->inline()
                    ->live()
                    ->required(),
                Select::make('recipient_ids')
                    ->label('Příjemci')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options(fn (CourseSeries|OneOffEvent $record): array => static::recipientOptions($record))
                    ->visible(fn (Get $get): bool => $get('audience') === 'selected'),
                ToggleButtons::make('mode')
                    ->label('Režim')
                    ->options([
                        'custom' => 'Vlastní e-mail',
                        'template' => 'Šablona',
                    ])
                    ->default('custom')
                    ->inline()
                    ->live()
                    ->required(),
                Select::make('template_key')
                    ->label('Šablona e-mailu')
                    ->required()
                    ->options(static::broadcastTemplateOptions())
                    ->helperText('Nabízené jsou jen šablony, které dávají smysl hromadně.')
                    ->visible(fn (Get $get): bool => $get('mode') === 'template'),
                TextInput::make('subject')
                    ->label('Předmět')
                    ->required()
                    ->visible(fn (Get $get): bool => $get('mode') === 'custom'),
                EmailFields::richText('body', 'Text e-mailu', required: true)
                    ->visible(fn (Get $get): bool => $get('mode') === 'custom'),
                ...CopyRecipientsFields::make('Kopie se přidá ke každému odeslanému e-mailu — u větší skupiny to znamená hodně zpráv.'),
            ])
            ->action(function (CourseSeries|OneOffEvent $record, array $data): void {
                $recipients = static::resolveRecipients($record, $data);

                if ($recipients->isEmpty()) {
                    Notification::make()
                        ->title('Nebyl vybrán žádný platný příjemce.')
                        ->warning()
                        ->send();

                    return;
                }

                $isTemplate = ($data['mode'] ?? 'custom') === 'template';

                SendBulkParticipantEmailJob::dispatch(
                    signupClass: $recipients->first()::class,
                    signupIds: $recipients->modelKeys(),
                    templateKey: $isTemplate ? $data['template_key'] : null,
                    subject: $isTemplate ? null : $data['subject'],
                    bodyHtml: $isTemplate ? null : $data['body'],
                    senderId: auth()->id(),
                    copies: CopyRecipients::fromFormData($data),
                );

                Notification::make()
                    ->title('Odesílání spuštěno')
                    ->body('Zpráva míří na '.$recipients->count().' příjemců. Až budou odeslány, dáme vám vědět.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return Collection<int, CourseEnrollment|OneOffEventBooking>
     */
    private static function participants(CourseSeries|OneOffEvent $record): Collection
    {
        return $record instanceof CourseSeries
            ? $record->activeTakers()->with('client')->get()
            : $record->bookings()->with('client')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, CourseEnrollment|OneOffEventBooking>
     */
    private static function resolveRecipients(CourseSeries|OneOffEvent $record, array $data): Collection
    {
        $participants = static::participants($record)
            ->filter(fn (CourseEnrollment|OneOffEventBooking $signup): bool => filled($signup->emailRecipientAddress()));

        if (($data['audience'] ?? 'all') === 'all') {
            return $participants->values();
        }

        $chosen = array_map('strval', $data['recipient_ids'] ?? []);

        return $participants
            ->filter(fn (CourseEnrollment|OneOffEventBooking $signup): bool => in_array((string) $signup->getKey(), $chosen, true))
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private static function recipientOptions(CourseSeries|OneOffEvent $record): array
    {
        return static::participants($record)
            ->filter(fn (CourseEnrollment|OneOffEventBooking $signup): bool => filled($signup->emailRecipientAddress()))
            ->mapWithKeys(fn (CourseEnrollment|OneOffEventBooking $signup): array => [
                (string) $signup->getKey() => ($signup->emailRecipientName() ?? 'Bez jména').' — '.$signup->emailRecipientAddress(),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function broadcastTemplateOptions(): array
    {
        $options = [];

        foreach (self::BROADCAST_KEYS as $key) {
            $options[$key->value] = $key->label();
        }

        return $options;
    }
}
