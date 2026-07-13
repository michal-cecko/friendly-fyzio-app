<?php

namespace App\Enums;

use App\Support\Settings;

/**
 * The fixed set of transactional email triggers. Each case maps 1:1 to a seeded
 * EmailTemplate row; the value is the stable `key` stored in the database and used
 * by sending code to locate the right template.
 */
enum EmailTemplateKey: string
{
    case ReservationPending = 'reservation_pending';
    case ReservationConfirmed = 'reservation_confirmed';
    case ReservationReminder = 'reservation_reminder';
    case ReservationCancelled = 'reservation_cancelled';
    case ReservationChanged = 'reservation_changed';
    case ReservationAutoCancelled = 'reservation_auto_cancelled';
    case ReservationStornoPayment = 'reservation_storno_payment';
    case ReservationDoctorNote = 'reservation_doctor_note';

    // Therapist-facing notifications (recipient is the therapist, not the client).
    case TherapistReservationCreated = 'therapist_reservation_created';
    case TherapistReservationConfirmed = 'therapist_reservation_confirmed';
    case TherapistReservationCancelled = 'therapist_reservation_cancelled';
    case TherapistReservationChanged = 'therapist_reservation_changed';
    case TherapistReservationAutoCancelled = 'therapist_reservation_auto_cancelled';
    case TherapistPaymentReceived = 'therapist_payment_received';
    case TherapistPaymentOverdue = 'therapist_payment_overdue';

    public function label(): string
    {
        return match ($this) {
            self::ReservationPending => 'Rezervace čeká na potvrzení',
            self::ReservationConfirmed => 'Rezervace potvrzena',
            self::ReservationReminder => 'Připomínka rezervace',
            self::ReservationCancelled => 'Zrušení rezervace',
            self::ReservationChanged => 'Změna rezervace',
            self::ReservationAutoCancelled => 'Automatické zrušení rezervace',
            self::ReservationStornoPayment => 'Storno – platba poplatku',
            self::ReservationDoctorNote => 'Storno – potvrzení od lékaře',
            self::TherapistReservationCreated => 'Nová rezervace (terapeut)',
            self::TherapistReservationConfirmed => 'Potvrzený termín (terapeut)',
            self::TherapistReservationCancelled => 'Zrušení rezervace klientem (terapeut)',
            self::TherapistReservationChanged => 'Změna rezervace klientem (terapeut)',
            self::TherapistReservationAutoCancelled => 'Automatické zrušení termínu (terapeut)',
            self::TherapistPaymentReceived => 'Přijatá platba (terapeut)',
            self::TherapistPaymentOverdue => 'Platba po splatnosti (terapeut)',
        };
    }

    /**
     * Whether the recipient of this trigger is the therapist (vs. the client).
     */
    public function isTherapistFacing(): bool
    {
        return match ($this) {
            self::TherapistReservationCreated,
            self::TherapistReservationConfirmed,
            self::TherapistReservationCancelled,
            self::TherapistReservationChanged,
            self::TherapistReservationAutoCancelled,
            self::TherapistPaymentReceived,
            self::TherapistPaymentOverdue => true,
            default => false,
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::ReservationPending => 'Vaše rezervace čeká na potvrzení',
            self::ReservationConfirmed => 'Vaše rezervace byla potvrzena',
            self::ReservationReminder => 'Připomínka: zítra vás čekáme',
            self::ReservationCancelled => 'Vaše rezervace byla zrušena',
            self::ReservationChanged => 'Vaše rezervace byla změněna',
            self::ReservationAutoCancelled => 'Vaše rezervace byla automaticky zrušena',
            self::ReservationStornoPayment => 'Storno poplatek k úhradě',
            self::ReservationDoctorNote => 'Doručte prosím potvrzení od lékaře',
            self::TherapistReservationCreated => 'Nová rezervace od klienta',
            self::TherapistReservationConfirmed => 'Termín byl potvrzen',
            self::TherapistReservationCancelled => 'Klient zrušil rezervaci',
            self::TherapistReservationChanged => 'Klient změnil rezervaci',
            self::TherapistReservationAutoCancelled => 'Termín byl automaticky zrušen',
            self::TherapistPaymentReceived => 'Platba od klienta byla přijata',
            self::TherapistPaymentOverdue => 'Klient má neuhrazenou platbu po splatnosti',
        };
    }

    /**
     * Placeholder tokens an admin may use in the email body ({{ token }}), keyed by
     * token name with a Czech description.
     *
     * @return array<string, string>
     */
    public function tokens(): array
    {
        if ($this->isTherapistFacing()) {
            return $this->therapistTokens();
        }

        $base = [
            'jmeno' => 'Jméno klienta',
            'sluzba' => 'Název služby',
            'terapeut' => 'Jméno terapeuta',
            'termin' => 'Datum a čas',
            'odkaz' => 'Odkaz na správu rezervace',
            'pripominka_hodin' => 'Připomínka – hodin před termínem',
            'auto_zruseni_hodin' => 'Auto-zrušení – hodin na potvrzení',
            'storno_hodin' => 'Bezplatné storno – hodin před termínem',
            'potvrzeni_hodin' => 'Výzva k potvrzení – hodin předem',
            'storno_procenta' => 'Storno poplatek – %',
        ];

        return match ($this) {
            self::ReservationPending,
            self::ReservationConfirmed,
            self::ReservationReminder => [
                ...$base,
                'misto' => 'Místo / adresa',
            ],
            self::ReservationCancelled,
            self::ReservationAutoCancelled => [
                ...$base,
                'duvod' => 'Důvod zrušení',
            ],
            self::ReservationChanged => [
                ...$base,
                'telefon' => 'Telefon klienta',
                'email' => 'E-mail klienta',
                'puvodni_sluzba' => 'Původní služba',
                'puvodni_terapeut' => 'Původní terapeut',
                'puvodni_termin' => 'Původní datum a čas',
            ],
            self::ReservationStornoPayment => [
                ...$base,
                'castka' => 'Výše storno poplatku',
                'iban' => 'Číslo účtu (IBAN)',
                'vs' => 'Variabilní symbol',
                'qr' => 'QR platba (obrázek)',
            ],
            self::ReservationDoctorNote => [
                ...$base,
                'misto' => 'Místo / adresa',
            ],
            default => $base,
        };
    }

    /**
     * Sample values used to render the in-admin preview (and any brick that reads
     * live reservation data, until real sending is wired up).
     *
     * @return array<string, string>
     */
    public function sampleContext(): array
    {
        if ($this->isTherapistFacing()) {
            return $this->therapistSampleContext();
        }

        $base = [
            'jmeno' => 'Jana',
            'sluzba' => 'Klasická masáž (60 min)',
            'terapeut' => 'Mgr. Petra Nováková',
            'termin' => '15. dubna 2026, 10:00',
            'odkaz' => '#',
            'pripominka_hodin' => (string) Settings::reminderHours(),
            'auto_zruseni_hodin' => (string) Settings::autoCancelHours(),
            'storno_hodin' => (string) Settings::cancelBeforeHours(),
            'potvrzeni_hodin' => (string) Settings::confirmationHours(),
            'storno_procenta' => (string) Settings::stornoFeePercent(),
        ];

        return match ($this) {
            self::ReservationPending,
            self::ReservationConfirmed,
            self::ReservationReminder => [
                ...$base,
                'misto' => 'Vodičkova 20, Praha',
            ],
            self::ReservationCancelled => [
                ...$base,
                'sluzba' => 'Lymfodrenáž (60 min)',
                'termin' => '18. dubna 2026, 09:00',
                'duvod' => 'Zrušeno klientem',
            ],
            self::ReservationAutoCancelled => [
                ...$base,
                'sluzba' => 'Lymfodrenáž (60 min)',
                'termin' => '18. dubna 2026, 09:00',
                'duvod' => 'Automatické zrušení – nepotvrzená účast',
            ],
            self::ReservationChanged => [
                ...$base,
                'sluzba' => 'Sportovní masáž (60 min)',
                'termin' => '22. dubna 2026, 15:00',
                'telefon' => '+420 604 123 456',
                'email' => 'jana@example.cz',
                'puvodni_sluzba' => 'Sportovní masáž (60 min)',
                'puvodni_terapeut' => 'Bc. Jan Dvořák',
                'puvodni_termin' => '20. dubna 2026, 11:00',
            ],
            self::ReservationStornoPayment => [
                ...$base,
                'sluzba' => 'Lymfodrenáž (60 min)',
                'termin' => '18. dubna 2026, 09:00',
                'castka' => '600',
                'iban' => 'CZ65 0800 0000 1920 0014 5399',
                'vs' => '1042',
                'qr' => '#',
            ],
            self::ReservationDoctorNote => [
                ...$base,
                'sluzba' => 'Lymfodrenáž (60 min)',
                'termin' => '18. dubna 2026, 09:00',
                'misto' => 'Vodičkova 20, Praha',
            ],
            default => $base,
        };
    }

    /**
     * Placeholder tokens for the therapist-facing e-mails. The greeting addresses the
     * therapist ({{ jmeno }}) and the details describe the client + appointment.
     *
     * @return array<string, string>
     */
    private function therapistTokens(): array
    {
        $base = [
            'jmeno' => 'Jméno terapeuta',
            'sluzba' => 'Název služby',
            'klient' => 'Jméno klienta',
            'telefon_klienta' => 'Telefon klienta',
            'email_klienta' => 'E-mail klienta',
            'termin' => 'Datum a čas',
            'odkaz' => 'Odkaz do kalendáře / na rezervaci',
        ];

        return match ($this) {
            self::TherapistReservationCreated => [
                ...$base,
                'odkaz_potvrdit' => 'Odkaz pro potvrzení termínu',
            ],
            self::TherapistReservationCancelled => [
                ...$base,
                'storno_reseni' => 'Řešení storna (jak klient řeší poplatek)',
                'storno_castka' => 'Výše storno poplatku',
            ],
            self::TherapistReservationChanged => [
                ...$base,
                'puvodni_sluzba' => 'Původní služba',
                'puvodni_termin' => 'Původní datum a čas',
            ],
            self::TherapistReservationAutoCancelled => [
                ...$base,
                'auto_zruseni_hodin' => 'Auto-zrušení – hodin na potvrzení',
                'duvod' => 'Důvod zrušení',
            ],
            self::TherapistPaymentReceived => [
                'klient' => 'Jméno klienta',
                'za_co' => 'Za co (služba / položka)',
                'castka' => 'Uhrazená částka',
                'datum_platby' => 'Datum platby',
                'zpusob_platby' => 'Způsob platby',
                'odkaz_klient' => 'Odkaz na detail klienta',
            ],
            self::TherapistPaymentOverdue => [
                'klient' => 'Jméno klienta',
                'za_co' => 'Za co (služba / položka)',
                'castka' => 'Dlužná částka',
                'email_klienta' => 'E-mail klienta',
                'sluzba' => 'Název služby',
                'splatnost' => 'Splatnost',
            ],
            default => $base,
        };
    }

    /**
     * Sample values used to render the in-admin preview of the therapist e-mails.
     *
     * @return array<string, string>
     */
    private function therapistSampleContext(): array
    {
        $base = [
            'jmeno' => 'Petra',
            'sluzba' => 'Sportovní masáž (60 min)',
            'klient' => 'Jana Kováčová',
            'telefon_klienta' => '+420 604 123 456',
            'email_klienta' => 'jana.kovacova@email.cz',
            'termin' => '22. dubna 2026, 15:00',
            'odkaz' => '#',
        ];

        return match ($this) {
            self::TherapistReservationCreated => [
                ...$base,
                'odkaz_potvrdit' => '#',
            ],
            self::TherapistReservationCancelled => [
                ...$base,
                'termin' => '20. dubna 2026, 11:00',
                'storno_reseni' => 'Klient zaplatí storno',
                'storno_castka' => '500 Kč',
            ],
            self::TherapistReservationChanged => [
                ...$base,
                'puvodni_sluzba' => 'Sportovní masáž (60 min)',
                'puvodni_termin' => '20. dubna 2026, 11:00',
            ],
            self::TherapistReservationAutoCancelled => [
                ...$base,
                'termin' => '20. dubna 2026, 11:00',
                'auto_zruseni_hodin' => (string) Settings::autoCancelHours(),
                'duvod' => 'Automatické zrušení – nepotvrzená účast',
            ],
            self::TherapistPaymentReceived => [
                'klient' => 'Mario Svoboda',
                'za_co' => 'fyzioterapii individuální',
                'castka' => '1 250 Kč',
                'datum_platby' => '10. dubna 2026',
                'zpusob_platby' => 'Hotově',
                'odkaz_klient' => '#',
            ],
            self::TherapistPaymentOverdue => [
                'klient' => 'Tomáš Novák',
                'za_co' => 'fyzioterapii individuální',
                'castka' => '2 400 Kč',
                'email_klienta' => 'tomas.novak@email.cz',
                'sluzba' => 'Fyzioterapie individuální',
                'splatnost' => '5. 4. 2026 (po splatnosti!)',
            ],
            default => $base,
        };
    }
}
