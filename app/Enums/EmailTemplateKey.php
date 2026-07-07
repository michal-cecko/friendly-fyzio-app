<?php

namespace App\Enums;

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

    public function label(): string
    {
        return match ($this) {
            self::ReservationPending => 'Rezervace čeká na potvrzení',
            self::ReservationConfirmed => 'Rezervace potvrzena terapeutem',
            self::ReservationReminder => 'Připomínka rezervace',
            self::ReservationCancelled => 'Zrušení rezervace',
            self::ReservationChanged => 'Změna rezervace',
            self::ReservationAutoCancelled => 'Automatické zrušení rezervace',
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
        $base = [
            'jmeno' => 'Jméno klienta',
            'sluzba' => 'Název služby',
            'terapeut' => 'Jméno terapeuta',
            'termin' => 'Datum a čas',
            'odkaz' => 'Odkaz na správu rezervace',
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
        $base = [
            'jmeno' => 'Jana',
            'sluzba' => 'Klasická masáž (60 min)',
            'terapeut' => 'Mgr. Petra Nováková',
            'termin' => '15. dubna 2026, 10:00',
            'odkaz' => '#',
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
        };
    }
}
