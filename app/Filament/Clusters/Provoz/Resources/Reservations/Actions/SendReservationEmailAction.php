<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\Payments\PaymentEmailTokens;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Manually (re)sends any reservation lifecycle e-mail for this reservation — to the
 * client or the therapist, chosen from the CMS templates. The payment/storno e-mails
 * are only offered when the reservation already has an unpaid payment (they carry its
 * real amount + QR); other context-specific tokens (storno resolution, original
 * values) render blank on a manual send.
 */
class SendReservationEmailAction extends Action
{
    /** @var list<EmailTemplateKey> */
    private const CLIENT_KEYS = [
        EmailTemplateKey::ReservationPending,
        EmailTemplateKey::ReservationCreated,
        EmailTemplateKey::ReservationAutoConfirmed,
        EmailTemplateKey::ReservationConfirmed,
        EmailTemplateKey::ReservationReminder,
        EmailTemplateKey::ReservationCancelled,
        EmailTemplateKey::ReservationDoctorNote,
    ];

    /** @var list<EmailTemplateKey> */
    private const THERAPIST_KEYS = [
        EmailTemplateKey::TherapistReservationCreated,
        EmailTemplateKey::TherapistReservationConfirmed,
        EmailTemplateKey::TherapistReservationCancelled,
        EmailTemplateKey::TherapistReservationChanged,
        EmailTemplateKey::TherapistReservationAutoCancelled,
    ];

    /** @var list<EmailTemplateKey> */
    private const PAYMENT_KEYS = [
        EmailTemplateKey::ReservationStornoPayment,
        EmailTemplateKey::ReservationUnpaid,
        EmailTemplateKey::ReservationNoShow,
    ];

    public static function getDefaultName(): ?string
    {
        return 'sendReservationEmail';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Odeslat e-mail')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->modalHeading('Odeslat e-mail k rezervaci')
            ->modalSubmitActionLabel('Odeslat')
            ->visible(fn (Reservation $record): bool => ! $record->trashed())
            ->schema([
                Select::make('template_key')
                    ->label('Šablona e-mailu')
                    ->required()
                    ->searchable()
                    ->options(fn (Reservation $record): array => self::optionGroups($record))
                    ->helperText('Platební e-maily jsou dostupné, jen když má rezervace nezaplacenou platbu — vytvořte ji přes „Vyžádat platbu".'),
            ])
            ->action(function (Reservation $record, array $data): void {
                $key = EmailTemplateKey::from($data['template_key']);
                $extra = self::extraTokens($record, $key);

                if ($key->isTherapistFacing()) {
                    $record->therapist?->user?->notify(new TherapistReservationTemplateNotification($record, $key, $extra));
                } else {
                    $record->client?->notify(new ReservationTemplateNotification($record, $key, $extra));
                }

                Notification::make()
                    ->title('E-mail byl odeslán.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function optionGroups(Reservation $record): array
    {
        $groups = [
            'Klient' => self::keyOptions(self::CLIENT_KEYS),
            'Terapeut' => self::keyOptions(self::THERAPIST_KEYS),
        ];

        if ($record->payments()->where('status', PaymentStatus::Unpaid->value)->exists()) {
            $groups['Platba'] = self::keyOptions(self::PAYMENT_KEYS);
        }

        return $groups;
    }

    /**
     * @param  list<EmailTemplateKey>  $keys
     * @return array<string, string>
     */
    private static function keyOptions(array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (EmailTemplateKey $key): array => [$key->value => $key->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function extraTokens(Reservation $record, EmailTemplateKey $key): array
    {
        if (in_array($key, self::PAYMENT_KEYS, true)) {
            $payment = $record->payments()->where('status', PaymentStatus::Unpaid->value)->latest()->first();

            return $payment !== null ? PaymentEmailTokens::for($payment) : [];
        }

        if ($key === EmailTemplateKey::TherapistReservationCreated) {
            return ['odkaz_potvrdit' => ReservationResource::getUrl('view', ['record' => $record])];
        }

        return [];
    }
}
