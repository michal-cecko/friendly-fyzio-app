<?php

namespace App\Support\Emails;

use App\Contracts\Emailable;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\Payments\PaymentEmailTokens;

/**
 * Resolves and dispatches the CMS reservation lifecycle e-mails for a manual (re)send
 * from the admin. The payment/storno e-mails are only offered when the reservation has
 * an unpaid payment (they carry its real amount + QR); other context-specific tokens
 * (storno resolution, original values) render blank on a manual send.
 *
 * Backs {@see Reservation}'s {@see Emailable} implementation — kept in the
 * support layer so the domain model stays free of Filament (the confirm-link token needs
 * the admin resource URL).
 */
class ReservationEmailer
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

    /**
     * @return array<string, array<string, string>>
     */
    public static function templateGroups(Reservation $reservation): array
    {
        $groups = [
            'Klient' => self::keyOptions(self::CLIENT_KEYS),
            'Terapeut' => self::keyOptions(self::THERAPIST_KEYS),
        ];

        if (self::hasUnpaidPayment($reservation)) {
            $groups['Platba'] = self::keyOptions(self::PAYMENT_KEYS);
        }

        return $groups;
    }

    public static function send(Reservation $reservation, EmailTemplateKey $key, ?CopyRecipients $copies = null): void
    {
        $extra = self::extraTokens($reservation, $key);

        if ($key->isTherapistFacing()) {
            $reservation->therapist?->user?->notify(new TherapistReservationTemplateNotification($reservation, $key, $extra, $copies));

            return;
        }

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, $key, $extra, $copies));
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
    private static function extraTokens(Reservation $reservation, EmailTemplateKey $key): array
    {
        if (in_array($key, self::PAYMENT_KEYS, true)) {
            $payment = $reservation->payments()->where('status', PaymentStatus::Unpaid->value)->latest()->first();

            return $payment !== null ? PaymentEmailTokens::for($payment) : [];
        }

        if ($key === EmailTemplateKey::TherapistReservationCreated) {
            return ['odkaz_potvrdit' => ReservationResource::getUrl('view', ['record' => $reservation])];
        }

        return [];
    }

    private static function hasUnpaidPayment(Reservation $reservation): bool
    {
        return $reservation->payments()->where('status', PaymentStatus::Unpaid->value)->exists();
    }
}
