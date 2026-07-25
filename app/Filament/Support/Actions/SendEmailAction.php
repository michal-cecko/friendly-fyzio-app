<?php

namespace App\Filament\Support\Actions;

use App\Contracts\Emailable;
use App\Enums\EmailTemplateKey;
use App\Filament\Support\Schemas\CopyRecipientsFields;
use App\Listeners\LogSentEmail;
use App\Mason\Support\EmailFields;
use App\Notifications\CustomEmailNotification;
use App\Support\Emails\CopyRecipients;
use App\Support\Emails\SentEmailReceipt;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Shared "Odeslat e-mail" action for any {@see Emailable} record (reservation,
 * enrollment/registration/booking, user). Offers two modes:
 *
 *  - **Šablona** — (re)send a CMS lifecycle template picked from the record's
 *    {@see Emailable::emailTemplateGroups()}, dispatched via the record itself.
 *  - **Vlastní e-mail** — compose a free-form message (recipient, CC, BCC, subject,
 *    rich body) wrapped in the fixed FriendlyFyzio layout and sent through
 *    {@see CustomEmailNotification}.
 *
 * Both paths flow through Laravel notifications, so every send is logged automatically
 * by {@see LogSentEmail}.
 */
class SendEmailAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendEmail';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Odeslat e-mail')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->modalHeading('Odeslat e-mail')
            ->modalSubmitActionLabel('Odeslat')
            ->visible(fn (Model $record): bool => $record instanceof Emailable && $record->emailRecipientAddress() !== null)
            ->schema([
                ToggleButtons::make('mode')
                    ->label('Režim')
                    ->options([
                        'template' => 'Šablona',
                        'custom' => 'Vlastní e-mail',
                    ])
                    ->inline()
                    ->live()
                    ->default(fn (Model $record): string => self::hasTemplates($record) ? 'template' : 'custom'),
                Select::make('template_key')
                    ->label('Šablona e-mailu')
                    ->required()
                    ->searchable()
                    ->options(fn (Model $record): array => self::templateGroups($record))
                    ->helperText('Platební e-maily jsou dostupné, jen když má záznam nezaplacenou platbu.')
                    ->visible(fn (Get $get, Model $record): bool => $get('mode') === 'template' && self::hasTemplates($record)),
                Group::make([
                    TextInput::make('recipient')
                        ->label('Příjemce')
                        ->email()
                        ->required()
                        ->default(fn (Model $record): ?string => self::recipient($record)),
                    TextInput::make('subject')
                        ->label('Předmět')
                        ->required(),
                    EmailFields::richText('body', 'Text e-mailu', required: true),
                ])
                    ->visible(fn (Get $get): bool => $get('mode') === 'custom'),
                // Copies apply to both modes: a resent template is worth archiving too.
                ...CopyRecipientsFields::make(),
            ])
            ->action(function (Model $record, array $data): void {
                $copies = CopyRecipients::fromFormData($data);

                if (($data['mode'] ?? 'template') === 'custom') {
                    $sender = auth()->user();

                    Notification::route('mail', $data['recipient'])
                        ->notify(new CustomEmailNotification(
                            record: $record,
                            emailSubject: $data['subject'],
                            bodyHtml: $data['body'],
                            copies: $copies,
                            replyToAddress: $sender?->email,
                            replyToName: $sender?->name,
                        ));
                } else {
                    /** @var Emailable $record */
                    $record->sendTemplateEmail(EmailTemplateKey::from($data['template_key']), $copies);
                }

                // Before launch only administrators actually receive mail, so
                // staff must not be told a message went out when it did not.
                if (config('mail.suppress_non_admin')) {
                    FilamentNotification::make()
                        ->title('E-mail nebyl odeslán.')
                        ->body('Odesílání e-mailů klientům a terapeutům je před spuštěním pozastaveno.')
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                FilamentNotification::make()
                    ->title('E-mail byl odeslán.')
                    ->success()
                    ->send();

                SentEmailReceipt::forCurrentUser($data['subject'] ?? 'E-mail');
            });
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function templateGroups(Model $record): array
    {
        return $record instanceof Emailable ? $record->emailTemplateGroups() : [];
    }

    private static function hasTemplates(Model $record): bool
    {
        return self::templateGroups($record) !== [];
    }

    private static function recipient(Model $record): ?string
    {
        return $record instanceof Emailable ? $record->emailRecipientAddress() : null;
    }
}
