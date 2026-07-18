<?php

namespace App\Support\ActivityLog;

use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Human-readable labels for activity-log entries: the event, the affected
 * resource type, and the actor (which may be the system or an online customer
 * when there is no authenticated causer).
 */
class ActivityPresenter
{
    /** @var array<string, string> */
    private const EVENT_LABELS = [
        'created' => 'Vytvořeno',
        'updated' => 'Upraveno',
        'deleted' => 'Smazáno',
        'restored' => 'Obnoveno',
    ];

    /** @var array<string, string> */
    private const EVENT_COLORS = [
        'created' => 'success',
        'updated' => 'warning',
        'deleted' => 'danger',
        'restored' => 'info',
    ];

    /**
     * Czech resource label keyed by morph alias AND by class basename, so both
     * morph-mapped subjects and those stored under their full class name resolve.
     */
    private const SUBJECT_LABELS = [
        'reservation' => 'Rezervace',
        'service' => 'Služba',
        'course' => 'Kurz',
        'course_series' => 'Běh kurzu',
        'course_enrollment' => 'Přihláška do kurzu',
        'workshop' => 'Workshop',
        'workshop_registration' => 'Přihláška na workshop',
        'one_time_lesson' => 'Jednorázová lekce',
        'one_time_lesson_booking' => 'Rezervace lekce',
        'Reservation' => 'Rezervace',
        'User' => 'Uživatel',
        'Service' => 'Služba',
        'ServiceCategory' => 'Kategorie služeb',
        'TherapistProfile' => 'Terapeut',
        'Room' => 'Ordinace',
        'Building' => 'Budova',
        'Payment' => 'Platba',
        'Invoice' => 'Faktura',
        'Course' => 'Kurz',
        'CourseSeries' => 'Běh kurzu',
        'CourseLesson' => 'Lekce kurzu',
        'CourseEnrollment' => 'Přihláška do kurzu',
        'Workshop' => 'Workshop',
        'WorkshopRegistration' => 'Přihláška na workshop',
        'OneTimeLesson' => 'Jednorázová lekce',
        'OneTimeLessonBooking' => 'Rezervace lekce',
        'LessonAttendance' => 'Účast na lekci',
        'CreditTransaction' => 'Kreditní transakce',
        'CreditAccount' => 'Kreditní účet',
        'GiftVoucher' => 'Dárkový poukaz',
        'Page' => 'Stránka',
        'Banner' => 'Banner',
        'Review' => 'Recenze',
        'InstagramConnection' => 'Instagram',
        'EmailTemplate' => 'E-mailová šablona',
        'Navigation' => 'Navigace',
        'ClientNote' => 'Poznámka klienta',
        'ClientProfile' => 'Profil klienta',
        'SubstituteToken' => 'Náhradní token',
        'WaitlistEntry' => 'Pořadník',
    ];

    /** Column/attribute key → Czech label, shown in the change diff. */
    private const ATTRIBUTE_LABELS = [
        'name' => 'Název',
        'title' => 'Název',
        'slug' => 'URL adresa',
        'description' => 'Popis',
        'content' => 'Obsah',
        'note' => 'Poznámka',
        'notes' => 'Poznámka',
        'internal_note' => 'Interní poznámka',
        'anamnesis' => 'Anamnéza',
        'price' => 'Cena',
        'amount' => 'Částka',
        'deposit' => 'Záloha',
        'storno_fee' => 'Storno poplatek',
        'balance' => 'Zůstatek',
        'capacity' => 'Kapacita',
        'duration' => 'Délka',
        'status' => 'Stav',
        'state' => 'Stav',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'role' => 'Role',
        'rating' => 'Hodnocení',
        'color' => 'Barva',
        'location' => 'Umístění',
        'visibility' => 'Viditelnost',
        'exam_type' => 'Typ vyšetření',
        'position' => 'Pořadí',
        'display_order' => 'Pořadí',
        'attended' => 'Účast',
        'active' => 'Aktivní',
        'is_active' => 'Aktivní',
        'acts_as_therapist' => 'Vystupuje jako terapeut',
        'token' => 'Token',
        'number' => 'Číslo',
        'invoice_number' => 'Číslo faktury',
        'method' => 'Způsob platby',
        'weight' => 'Váha',
        'height' => 'Výška',
        'occupation' => 'Povolání',
        'date_of_birth' => 'Datum narození',
        'reservation_date' => 'Datum rezervace',
        'lesson_date' => 'Datum lekce',
        'start_time' => 'Začátek',
        'end_time' => 'Konec',
        'start_date' => 'Datum od',
        'end_date' => 'Datum do',
        'published_at' => 'Publikováno',
        'paid_at' => 'Zaplaceno',
        'cancelled_at' => 'Zrušeno',
        'created_at' => 'Vytvořeno',
        'updated_at' => 'Upraveno',
        'deleted_at' => 'Smazáno',
        'email_verified_at' => 'Ověření e-mailu',
        'address' => 'Adresa',
        'address_city' => 'Město',
        'billing_address' => 'Fakturační adresa',
        'billing_name' => 'Fakturační jméno',
        'company_ico' => 'IČO',
        'company_dic' => 'DIČ',
        'service_id' => 'Služba',
        'service_category_id' => 'Kategorie služeb',
        'category_id' => 'Kategorie',
        'therapist_id' => 'Terapeut',
        'instructor_id' => 'Lektor',
        'room_id' => 'Ordinace',
        'building_id' => 'Budova',
        'client_id' => 'Klient',
        'user_id' => 'Uživatel',
        'causer_id' => 'Autor změny',
        'course_id' => 'Kurz',
        'series_id' => 'Běh kurzu',
        'source_series_id' => 'Zdrojový běh kurzu',
        'workshop_id' => 'Workshop',
        'lesson_id' => 'Lekce',
        'enrollment_id' => 'Přihláška',
        'payable_type' => 'Typ předmětu platby',
        'payable_id' => 'Předmět platby',
        'parent_id' => 'Nadřazená položka',
        'navigation_id' => 'Navigace',
    ];

    /** A human-readable Czech label for a changed attribute key. */
    public static function attributeLabel(string $key): string
    {
        if (isset(self::ATTRIBUTE_LABELS[$key])) {
            return self::ATTRIBUTE_LABELS[$key];
        }

        $normalized = preg_replace('/_id$/', '', $key) ?? $key;

        return ucfirst(str_replace('_', ' ', $normalized));
    }

    public static function eventLabel(?string $event): string
    {
        return self::EVENT_LABELS[$event] ?? ($event ?? '—');
    }

    public static function eventColor(?string $event): string
    {
        return self::EVENT_COLORS[$event] ?? 'gray';
    }

    public static function subjectLabel(?string $subjectType): string
    {
        if ($subjectType === null) {
            return '—';
        }

        return self::SUBJECT_LABELS[$subjectType]
            ?? self::SUBJECT_LABELS[class_basename($subjectType)]
            ?? class_basename($subjectType);
    }

    /**
     * A human-readable name for the affected record. Prefers the live record's
     * current title, then the title snapshot stored on the log (survives
     * deletion), and finally the truncated UUID as a last resort.
     */
    public static function subjectTitle(Activity $activity): string
    {
        $subject = $activity->subject;

        if ($subject !== null && method_exists($subject, 'logTitle')) {
            $title = $subject->logTitle();

            if (is_string($title) && $title !== '') {
                return $title;
            }
        }

        // Deleted/legacy rows: the description holds a title snapshot, unless it
        // is just the bare event keyword left over from before titles were logged.
        $description = $activity->description;

        if (is_string($description) && $description !== '' && ! isset(self::EVENT_LABELS[$description])) {
            return $description;
        }

        // Last resort before the UUID: recover a name-like field from the snapshot.
        $snapshot = ($activity->attribute_changes['old'] ?? [])
            + ($activity->attribute_changes['attributes'] ?? []);

        foreach (['name', 'title', 'invoice_number', 'code'] as $key) {
            if (! empty($snapshot[$key]) && is_string($snapshot[$key])) {
                return $snapshot[$key];
            }
        }

        return $activity->subject_id === null
            ? '—'
            : substr((string) $activity->subject_id, 0, 8).'…';
    }

    /**
     * A link to the affected record's own Filament page (view, else edit), or
     * null when the record no longer exists or has no registered resource.
     */
    public static function subjectUrl(Activity $activity): ?string
    {
        $subject = $activity->subject;

        if ($subject === null) {
            return null;
        }

        foreach (Filament::getResources() as $resource) {
            if ($resource::getModel() !== $subject::class) {
                continue;
            }

            foreach (['view', 'edit'] as $page) {
                try {
                    return $resource::getUrl($page, ['record' => $subject]);
                } catch (Throwable) {
                    continue;
                }
            }

            try {
                return $resource::getUrl('index');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    public static function causerLabel(Activity $activity): string
    {
        if ($activity->causer !== null) {
            return (string) ($activity->causer->name ?? 'Uživatel');
        }

        // No authenticated actor: scheduled commands vs. the public booking flow.
        return $activity->event === 'created' && $activity->subject_type === 'reservation'
            ? 'Zákazník (online)'
            : 'Systém';
    }
}
