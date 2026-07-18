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
    case ReservationCreated = 'reservation_created';
    case ReservationAutoConfirmed = 'reservation_auto_confirmed';
    case ReservationConfirmed = 'reservation_confirmed';
    case ReservationReminder = 'reservation_reminder';
    case ReservationCancelled = 'reservation_cancelled';
    case ReservationChanged = 'reservation_changed';
    case ReservationAutoCancelled = 'reservation_auto_cancelled';
    case ReservationStornoPayment = 'reservation_storno_payment';
    case ReservationDoctorNote = 'reservation_doctor_note';
    case ReservationUnpaid = 'reservation_unpaid';
    case ReservationNoShow = 'reservation_no_show';

    // Payment & invoicing e-mails (client-facing).
    case PaymentReceived = 'payment_received';
    case PaymentOverdue = 'payment_overdue';
    case InvoiceIssued = 'invoice_issued';

    // Course / one-time lesson / workshop sign-up e-mails (client-facing).
    case CourseEnrollmentReceived = 'course_enrollment_received';
    case WorkshopRegistrationReceived = 'workshop_registration_received';
    case LessonBookingReceived = 'lesson_booking_received';
    case EnrollmentAutoCancelled = 'enrollment_auto_cancelled';
    case WaitlistJoined = 'waitlist_joined';
    case WaitlistSpotAvailable = 'waitlist_spot_available';
    case CourseRegistrationOpened = 'course_registration_opened';
    case EnrollmentCancelledByClient = 'enrollment_cancelled';
    case EnrollmentCancelledByClinic = 'enrollment_cancelled_by_clinic';
    case SubstituteTokenGenerated = 'substitute_token_generated';
    case SubstituteTokenRedeemed = 'substitute_token_redeemed';

    // Account & auth e-mails (client-facing). These replace the framework/Filament
    // default notifications so the copy is editable in the dashboard; the {{ odkaz }}
    // token carries the signed action URL produced by the auth flow.
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
    case EmailChangeVerification = 'email_change_verification';
    case AccountCreated = 'account_created';
    case ReviewRequest = 'review_request';

    // Therapist-facing notifications (recipient is the therapist, not the client).
    case TherapistReservationCreated = 'therapist_reservation_created';
    case TherapistReservationConfirmed = 'therapist_reservation_confirmed';
    case TherapistReservationCancelled = 'therapist_reservation_cancelled';
    case TherapistReservationChanged = 'therapist_reservation_changed';
    case TherapistReservationAutoCancelled = 'therapist_reservation_auto_cancelled';
    case TherapistPaymentReceived = 'therapist_payment_received';
    case TherapistPaymentOverdue = 'therapist_payment_overdue';
    case TherapistEnrollmentCreated = 'therapist_enrollment_created';

    public function label(): string
    {
        return match ($this) {
            self::ReservationPending => 'Rezervace čeká na potvrzení',
            self::ReservationCreated => 'Rezervace vytvořena',
            self::ReservationAutoConfirmed => 'Rezervace automaticky potvrzena',
            self::ReservationConfirmed => 'Rezervace potvrzena',
            self::ReservationReminder => 'Připomínka rezervace',
            self::ReservationCancelled => 'Zrušení rezervace',
            self::ReservationChanged => 'Změna rezervace',
            self::ReservationAutoCancelled => 'Automatické zrušení rezervace',
            self::ReservationStornoPayment => 'Storno – platba poplatku',
            self::ReservationDoctorNote => 'Storno – potvrzení od lékaře',
            self::ReservationUnpaid => 'Nezaplacený termín',
            self::ReservationNoShow => 'Nedostavení na termín',
            self::PaymentReceived => 'Platba přijata',
            self::PaymentOverdue => 'Platba po splatnosti',
            self::InvoiceIssued => 'Faktura vystavena',
            self::CourseEnrollmentReceived => 'Přihláška na kurz přijata',
            self::WorkshopRegistrationReceived => 'Registrace na workshop přijata',
            self::LessonBookingReceived => 'Rezervace lekce přijata',
            self::EnrollmentAutoCancelled => 'Automatické zrušení přihlášky (nezaplaceno)',
            self::WaitlistJoined => 'Zařazení na čekací listinu',
            self::WaitlistSpotAvailable => 'Uvolněné místo z čekací listiny',
            self::CourseRegistrationOpened => 'Otevření přihlašování na kurz',
            self::EnrollmentCancelledByClient => 'Odhlášení klientem (klientská zóna)',
            self::EnrollmentCancelledByClinic => 'Zrušení přihlášky klinikou',
            self::SubstituteTokenGenerated => 'Náhradní vstup vydán (omluvená lekce)',
            self::SubstituteTokenRedeemed => 'Náhradní vstup uplatněn',
            self::EmailVerification => 'Ověření e-mailu',
            self::PasswordReset => 'Obnovení hesla',
            self::EmailChangeVerification => 'Ověření změny e-mailu',
            self::AccountCreated => 'Vytvoření účtu',
            self::ReviewRequest => 'Žádost o recenzi',
            self::TherapistReservationCreated => 'Nová rezervace (terapeut)',
            self::TherapistReservationConfirmed => 'Potvrzený termín (terapeut)',
            self::TherapistReservationCancelled => 'Zrušení rezervace klientem (terapeut)',
            self::TherapistReservationChanged => 'Změna rezervace klientem (terapeut)',
            self::TherapistReservationAutoCancelled => 'Automatické zrušení termínu (terapeut)',
            self::TherapistPaymentReceived => 'Přijatá platba (terapeut)',
            self::TherapistPaymentOverdue => 'Platba po splatnosti (terapeut)',
            self::TherapistEnrollmentCreated => 'Nová přihláška (lektor)',
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
            self::TherapistPaymentOverdue,
            self::TherapistEnrollmentCreated => true,
            default => false,
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::ReservationPending => 'Vaše rezervace čeká na potvrzení',
            self::ReservationCreated => 'Přijali jsme vaši rezervaci',
            self::ReservationAutoConfirmed => 'Vaše rezervace je potvrzena',
            self::ReservationConfirmed => 'Vaše rezervace byla potvrzena',
            self::ReservationReminder => 'Připomínka: zítra vás čekáme',
            self::ReservationCancelled => 'Vaše rezervace byla zrušena',
            self::ReservationChanged => 'Vaše rezervace byla změněna',
            self::ReservationAutoCancelled => 'Vaše rezervace byla automaticky zrušena',
            self::ReservationStornoPayment => 'Storno poplatek k úhradě',
            self::ReservationDoctorNote => 'Doručte prosím potvrzení od lékaře',
            self::ReservationUnpaid => 'Máte nezaplacený termín',
            self::ReservationNoShow => 'Nedostavili jste se na termín',
            self::PaymentReceived => 'Vaše platba byla přijata',
            self::PaymentOverdue => 'Upozornění: platba po splatnosti',
            self::InvoiceIssued => 'Zasíláme Vám fakturu',
            self::CourseEnrollmentReceived => 'Přijali jsme vaši přihlášku na kurz',
            self::WorkshopRegistrationReceived => 'Přijali jsme vaši registraci na workshop',
            self::LessonBookingReceived => 'Přijali jsme vaši rezervaci lekce',
            self::EnrollmentAutoCancelled => 'Vaše přihláška byla automaticky zrušena',
            self::WaitlistJoined => 'Jste na čekací listině',
            self::WaitlistSpotAvailable => 'Uvolnilo se místo — dokončete přihlášení',
            self::CourseRegistrationOpened => 'Otevřeli jsme přihlašování na kurz',
            self::EnrollmentCancelledByClient => 'Vaše odhlášení proběhlo',
            self::EnrollmentCancelledByClinic => 'Vaše přihláška byla zrušena',
            self::SubstituteTokenGenerated => 'Máte náhradní vstup za omluvenou lekci',
            self::SubstituteTokenRedeemed => 'Náhradní lekce je rezervována',
            self::EmailVerification => 'Ověřte svou e-mailovou adresu',
            self::PasswordReset => 'Obnovení hesla',
            self::EmailChangeVerification => 'Ověřte svou novou e-mailovou adresu',
            self::AccountCreated => 'Váš účet ve Friendly Fyzio',
            self::ReviewRequest => 'Jak jste byli spokojeni?',
            self::TherapistReservationCreated => 'Nová rezervace od klienta',
            self::TherapistReservationConfirmed => 'Termín byl potvrzen',
            self::TherapistReservationCancelled => 'Klient zrušil rezervaci',
            self::TherapistReservationChanged => 'Klient změnil rezervaci',
            self::TherapistReservationAutoCancelled => 'Termín byl automaticky zrušen',
            self::TherapistPaymentReceived => 'Platba od klienta byla přijata',
            self::TherapistPaymentOverdue => 'Klient má neuhrazenou platbu po splatnosti',
            self::TherapistEnrollmentCreated => 'Nová přihláška od klienta',
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
            self::ReservationCreated,
            self::ReservationAutoConfirmed,
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
            self::ReservationUnpaid,
            self::ReservationNoShow => [
                ...$base,
                'castka' => 'Částka k úhradě',
                'iban' => 'Číslo účtu (IBAN)',
                'vs' => 'Variabilní symbol',
                'qr' => 'QR platba (obrázek)',
                'splatnost' => 'Datum splatnosti',
            ],
            self::PaymentReceived => [
                'jmeno' => 'Jméno klienta',
                'za_co' => 'Za co bylo zaplaceno',
                'castka' => 'Uhrazená částka',
                'datum' => 'Datum platby',
                'zpusob_platby' => 'Způsob platby',
                'cislo_faktury' => 'Číslo faktury (je-li vystavena)',
                'odkaz' => 'Odkaz do klientské zóny',
            ],
            self::PaymentOverdue => [
                'jmeno' => 'Jméno klienta',
                'za_co' => 'Za co je dlužná částka',
                'castka' => 'Dlužná částka',
                'iban' => 'Číslo účtu (IBAN)',
                'vs' => 'Variabilní symbol',
                'zprava' => 'Zpráva pro příjemce',
                'splatnost' => 'Datum splatnosti',
                'qr' => 'QR platba (obrázek)',
            ],
            self::InvoiceIssued => [
                'jmeno' => 'Jméno klienta',
                'cislo_faktury' => 'Číslo faktury',
                'castka' => 'Celková částka',
                'splatnost' => 'Datum splatnosti',
                'zpusob_platby' => 'Způsob platby',
                'polozky_tabulka' => 'Tabulka položek faktury (doplní se automaticky)',
            ],
            self::CourseEnrollmentReceived => [
                'jmeno' => 'Jméno klienta',
                'kurz' => 'Název kurzu',
                'beh' => 'Název běhu',
                'obdobi' => 'Období běhu (od – do)',
                'rozvrh' => 'Nejbližší lekce běhu',
                'rezervace_hodin' => 'Rezervace místa – hodin na zaplacení',
                'castka' => 'Částka k úhradě',
                'iban' => 'Číslo účtu (IBAN)',
                'vs' => 'Variabilní symbol',
                'qr' => 'QR platba (obrázek)',
                'splatnost' => 'Datum splatnosti',
            ],
            self::WorkshopRegistrationReceived => [
                'jmeno' => 'Jméno klienta',
                'workshop' => 'Název workshopu',
                'termin' => 'Datum a čas konání',
                'misto' => 'Místo / adresa',
                'rezervace_hodin' => 'Rezervace místa – hodin na zaplacení',
                'castka' => 'Částka k úhradě',
                'iban' => 'Číslo účtu (IBAN)',
                'vs' => 'Variabilní symbol',
                'qr' => 'QR platba (obrázek)',
                'splatnost' => 'Datum splatnosti',
            ],
            self::LessonBookingReceived => [
                'jmeno' => 'Jméno klienta',
                'lekce' => 'Název lekce',
                'termin' => 'Datum a čas konání',
                'misto' => 'Místo / adresa',
                'rezervace_hodin' => 'Rezervace místa – hodin na zaplacení',
                'castka' => 'Částka k úhradě',
                'iban' => 'Číslo účtu (IBAN)',
                'vs' => 'Variabilní symbol',
                'qr' => 'QR platba (obrázek)',
                'splatnost' => 'Datum splatnosti',
            ],
            self::EnrollmentAutoCancelled => [
                'jmeno' => 'Jméno klienta',
                'nazev' => 'Název kurzu / lekce / workshopu',
                'termin' => 'Termín / období',
                'duvod' => 'Důvod zrušení',
            ],
            self::WaitlistJoined => [
                'jmeno' => 'Jméno klienta',
                'nazev' => 'Název kurzu / lekce / workshopu',
                'termin' => 'Termín / období',
                'poradi' => 'Pořadí na čekací listině',
            ],
            self::WaitlistSpotAvailable => [
                'jmeno' => 'Jméno klienta',
                'nazev' => 'Název kurzu / lekce / workshopu',
                'termin' => 'Termín / období',
                'rezervace_hodin' => 'Rezervace místa – hodin na zaplacení',
                'castka' => 'Částka k úhradě',
                'iban' => 'Číslo účtu (IBAN)',
                'vs' => 'Variabilní symbol',
                'qr' => 'QR platba (obrázek)',
                'splatnost' => 'Datum splatnosti',
            ],
            self::CourseRegistrationOpened => [
                'jmeno' => 'Jméno klienta',
                'kurz' => 'Název kurzu',
                'beh' => 'Název běhu',
                'obdobi' => 'Období běhu (od – do)',
                'odkaz' => 'Odkaz na stránku kurzu',
            ],
            self::EnrollmentCancelledByClient,
            self::EnrollmentCancelledByClinic => [
                'jmeno' => 'Jméno klienta',
                'nazev' => 'Název kurzu / lekce / workshopu',
                'termin' => 'Termín / období',
            ],
            self::SubstituteTokenGenerated => [
                'jmeno' => 'Jméno klienta',
                'kurz' => 'Název kurzu',
                'lekce' => 'Omluvená lekce (datum a čas)',
                'platnost' => 'Platnost náhradního vstupu do',
                'odkaz' => 'Odkaz na náhradní vstupy v klientské zóně',
            ],
            self::SubstituteTokenRedeemed => [
                'jmeno' => 'Jméno klienta',
                'kurz' => 'Název kurzu s náhradní lekcí',
                'lekce' => 'Náhradní lekce (datum a čas)',
                'misto' => 'Místo / adresa',
            ],
            self::EmailVerification, self::PasswordReset => [
                'jmeno' => 'Jméno příjemce',
                'odkaz' => 'Odkaz s akcí (doplní se automaticky)',
            ],
            self::EmailChangeVerification => [
                'jmeno' => 'Jméno příjemce',
                'email' => 'Nová e-mailová adresa',
                'odkaz' => 'Ověřovací odkaz (doplní se automaticky)',
            ],
            self::AccountCreated => [
                'jmeno' => 'Jméno klienta',
                'odkaz' => 'Odkaz na přihlášení',
            ],
            self::ReviewRequest => [
                'jmeno' => 'Jméno klienta',
                'cil' => 'Co recenzovat (název)',
                'intro' => 'Úvodní text',
                'odkaz' => 'Odkaz na formulář recenze',
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
            self::ReservationCreated,
            self::ReservationAutoConfirmed,
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
            self::ReservationUnpaid => [
                ...$base,
                'castka' => '800',
                'iban' => 'CZ65 0800 0000 1920 0014 5399',
                'vs' => '1042',
                'qr' => '#',
                'splatnost' => '23. dubna 2026',
            ],
            self::ReservationNoShow => [
                ...$base,
                'sluzba' => 'Přístrojová masáž (45 min)',
                'termin' => '18. dubna 2026, 09:00',
                'castka' => '500',
                'iban' => 'CZ65 0800 0000 1920 0014 5399',
                'vs' => '1043',
                'qr' => '#',
                'splatnost' => '25. dubna 2026',
            ],
            self::PaymentReceived => [
                'jmeno' => 'Mario',
                'za_co' => 'Fyzioterapie individuální – 10. dubna 2026',
                'castka' => '1 250 Kč',
                'datum' => '10. dubna 2026',
                'zpusob_platby' => 'Hotově',
                'cislo_faktury' => 'FF-2026-00412',
                'odkaz' => '#',
            ],
            self::PaymentOverdue => [
                'jmeno' => 'Tomáši',
                'za_co' => 'Fyzioterapie individuální – 28. března 2026',
                'castka' => '2 400',
                'iban' => 'CZ65 0800 0000 1920 0014 5399',
                'vs' => '20260415001',
                'zprava' => 'Tomáš Novák – dlužná částka',
                'splatnost' => '5. 4. 2026 (po splatnosti!)',
                'qr' => '#',
            ],
            self::InvoiceIssued => [
                'jmeno' => 'Lucie',
                'cislo_faktury' => 'FF-2026-00413',
                'castka' => '2 100 Kč',
                'splatnost' => '24. dubna 2026',
                'zpusob_platby' => 'Bankovní převod',
                'polozky_tabulka' => '',
            ],
            self::CourseEnrollmentReceived => [
                'jmeno' => 'Jana',
                'kurz' => 'Hormonální jóga',
                'beh' => 'leden–duben 2026',
                'obdobi' => '12. 01. 2026 – 27. 04. 2026',
                'rozvrh' => 'pondělí 9:00–10:00',
                'rezervace_hodin' => '48',
                'castka' => '2 200',
                'iban' => 'CZ65 0800 0000 1920 0014 5399',
                'vs' => '1051',
                'qr' => '#',
                'splatnost' => '14. ledna 2026',
            ],
            self::WorkshopRegistrationReceived => [
                'jmeno' => 'Jana',
                'workshop' => 'Baby massage workshop',
                'termin' => '22. března 2026, 9:00',
                'misto' => 'Zednická 1109/2, Ostrava',
                'rezervace_hodin' => '48',
                'castka' => '3 500',
                'iban' => 'CZ65 0800 0000 1920 0014 5399',
                'vs' => '1052',
                'qr' => '#',
                'splatnost' => '10. března 2026',
            ],
            self::LessonBookingReceived => [
                'jmeno' => 'Jana',
                'lekce' => 'Jin jóga',
                'termin' => '16. února 2026, 18:00',
                'misto' => 'Zednická 1109/2, Ostrava',
                'rezervace_hodin' => '48',
                'castka' => '450',
                'iban' => 'CZ65 0800 0000 1920 0014 5399',
                'vs' => '1053',
                'qr' => '#',
                'splatnost' => '10. února 2026',
            ],
            self::EnrollmentAutoCancelled => [
                'jmeno' => 'Jana',
                'nazev' => 'Hormonální jóga (leden–duben 2026)',
                'termin' => '12. 01. 2026 – 27. 04. 2026',
                'duvod' => 'Platba nebyla připsána v rezervační lhůtě',
            ],
            self::WaitlistJoined => [
                'jmeno' => 'Jana',
                'nazev' => 'Hormonální jóga (leden–duben 2026)',
                'termin' => '12. 01. 2026 – 27. 04. 2026',
                'poradi' => '3',
            ],
            self::WaitlistSpotAvailable => [
                'jmeno' => 'Jana',
                'nazev' => 'Hormonální jóga (leden–duben 2026)',
                'termin' => '12. 01. 2026 – 27. 04. 2026',
                'rezervace_hodin' => '48',
                'castka' => '2 200',
                'iban' => 'CZ65 0800 0000 1920 0014 5399',
                'vs' => '1054',
                'qr' => '#',
                'splatnost' => '20. ledna 2026',
            ],
            self::CourseRegistrationOpened => [
                'jmeno' => 'Jana',
                'kurz' => 'Hormonální jóga',
                'beh' => 'květen–srpen 2026',
                'obdobi' => '04. 05. 2026 – 24. 08. 2026',
                'odkaz' => '#',
            ],
            self::EnrollmentCancelledByClient => [
                'jmeno' => 'Jana',
                'nazev' => 'Hormonální jóga (leden–duben 2026)',
                'termin' => '12. 01. 2026 – 27. 04. 2026',
            ],
            self::SubstituteTokenGenerated => [
                'jmeno' => 'Jana',
                'kurz' => 'Hormonální jóga',
                'lekce' => '19. 01. 2026 · 18:00',
                'platnost' => '18. 02. 2026',
                'odkaz' => '#',
            ],
            self::SubstituteTokenRedeemed => [
                'jmeno' => 'Jana',
                'kurz' => 'Somatická jóga',
                'lekce' => '26. 01. 2026 · 17:00',
                'misto' => 'Zednická 1109/2, Ostrava',
            ],
            self::EmailVerification, self::PasswordReset => [
                'jmeno' => 'Jana',
                'odkaz' => '#',
            ],
            self::EmailChangeVerification => [
                'jmeno' => 'Jana',
                'email' => 'jana.nova@example.cz',
                'odkaz' => '#',
            ],
            self::AccountCreated => [
                'jmeno' => 'Jana',
                'odkaz' => '#',
            ],
            self::ReviewRequest => [
                'jmeno' => 'Jana',
                'cil' => 'návštěvu „Fyzioterapie individuální“',
                'intro' => 'Budeme moc rádi, když nám zanecháte krátkou recenzi.',
                'odkaz' => '#',
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
            self::TherapistEnrollmentCreated => [
                'jmeno' => 'Jméno lektora',
                'nazev' => 'Název kurzu / lekce / workshopu',
                'termin' => 'Termín / období',
                'klient' => 'Jméno klienta',
                'telefon_klienta' => 'Telefon klienta',
                'email_klienta' => 'E-mail klienta',
                'poznamka' => 'Poznámka klienta',
                'odkaz' => 'Odkaz na přihlášku v administraci',
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
            self::TherapistEnrollmentCreated => [
                'jmeno' => 'Petra',
                'nazev' => 'Hormonální jóga (leden–duben 2026)',
                'termin' => '12. 01. 2026 – 27. 04. 2026',
                'klient' => 'Jana Kováčová',
                'telefon_klienta' => '+420 604 123 456',
                'email_klienta' => 'jana.kovacova@email.cz',
                'poznamka' => 'Prosím o místo u okna.',
                'odkaz' => '#',
            ],
            default => $base,
        };
    }
}
