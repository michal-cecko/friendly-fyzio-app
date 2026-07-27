<?php

namespace App\Enums;

/**
 * The billable record types that can carry payments. Values are identical to the
 * morph-map aliases stored in `payments.payable_type` / `invoices.invoiceable_type`.
 */
enum PayableType: string
{
    case Reservation = 'reservation';
    case CourseEnrollment = 'course_enrollment';
    case LessonBooking = 'lesson_booking';

    /**
     * Storno fees reuse the reservation tokens but have their own wording,
     * so the template pair lives under dedicated settings keys.
     */
    public const STORNO_TITLE_KEY = 'invoices.item_title_reservation_storno';

    public const STORNO_DESCRIPTION_KEY = 'invoices.item_description_reservation_storno';

    public const STORNO_TITLE_DEFAULT = 'Storno poplatek – {{ sluzba }}';

    public const STORNO_DESCRIPTION_DEFAULT = 'Původní termín {{ datum }} v {{ cas }}';

    public function label(): string
    {
        return match ($this) {
            self::Reservation => 'Rezervace',
            self::CourseEnrollment => 'Přihláška na kurz',
            self::LessonBooking => 'Lekce',
        };
    }

    public function titleSettingKey(): string
    {
        return "invoices.item_title_{$this->value}";
    }

    public function descriptionSettingKey(): string
    {
        return "invoices.item_description_{$this->value}";
    }

    public function defaultTitleTemplate(): string
    {
        return match ($this) {
            self::Reservation => '{{ sluzba }} – {{ datum }}',
            self::CourseEnrollment => '{{ kurz }} – {{ beh }}',
            self::LessonBooking => '{{ nazev }} – {{ datum }}',
        };
    }

    public function defaultDescriptionTemplate(): string
    {
        return match ($this) {
            self::Reservation => '{{ cas }}, {{ terapeut }}',
            self::CourseEnrollment => '{{ obdobi }}',
            self::LessonBooking => '{{ cas }}',
        };
    }

    /**
     * Tokens available in the item title/description templates, with Czech
     * descriptions for the settings page.
     *
     * @return array<string, string>
     */
    public function tokens(): array
    {
        return match ($this) {
            self::Reservation => [
                'sluzba' => 'Název služby (Název pro fakturaci, jinak běžný název)',
                'datum' => 'Datum návštěvy',
                'cas' => 'Čas návštěvy',
                'terapeut' => 'Jméno terapeuta',
                'klient' => 'Jméno klienta',
            ],
            self::CourseEnrollment => [
                'kurz' => 'Název kurzu (Název pro fakturaci série, jinak název kurzu)',
                'beh' => 'Název série kurzu',
                'obdobi' => 'Období série (od – do)',
                'klient' => 'Jméno klienta',
            ],
            self::LessonBooking => [
                'nazev' => 'Název akce (Název pro fakturaci, jinak běžný název)',
                'datum' => 'Datum konání',
                'cas' => 'Čas začátku',
                'klient' => 'Jméno klienta',
            ],
        };
    }
}
