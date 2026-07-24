<?php

namespace App\Support\ActivityLog;

use Filament\Facades\Filament;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;
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
        'email_sent' => 'E-mail odeslán',
        'reservation_confirmed' => 'Rezervace potvrzena',
        'reservation_cancelled' => 'Rezervace zrušena',
        'reservation_edited' => 'Rezervace upravena',
        'reservation_no_show' => 'Nedostavil se',
        'reservation_storno_charged' => 'Storno poplatek',
        'reservation_auto_cancelled' => 'Automaticky zrušeno',
        'reservation_completed' => 'Rezervace vybavena',
        'reservation_reactivated' => 'Rezervace obnovena',
        'payment_requested' => 'Platba vyžádána',
        'payment_received' => 'Platba přijata',
        'invoice_issued' => 'Faktura vystavena',
        'bulk_deleted' => 'Hromadné smazání',
    ];

    /** @var array<string, string> */
    private const EVENT_COLORS = [
        'created' => 'success',
        'updated' => 'warning',
        'deleted' => 'danger',
        'restored' => 'info',
        'email_sent' => 'info',
        'reservation_confirmed' => 'success',
        'reservation_cancelled' => 'danger',
        'reservation_edited' => 'warning',
        'reservation_no_show' => 'warning',
        'reservation_storno_charged' => 'warning',
        'reservation_auto_cancelled' => 'danger',
        'reservation_completed' => 'gray',
        'reservation_reactivated' => 'success',
        'payment_requested' => 'info',
        'payment_received' => 'success',
        'invoice_issued' => 'info',
        'bulk_deleted' => 'danger',
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
        'one_off_event' => 'Jednorázová akce',
        'one_off_event_booking' => 'Přihláška na akci',
        'event_category' => 'Kategorie akcí',
        'Reservation' => 'Rezervace',
        'User' => 'Uživatel',
        'Service' => 'Služba',
        'ServiceCategory' => 'Kategorie služeb',
        'StaffProfile' => 'Terapeut',
        'Room' => 'Ordinace',
        'Building' => 'Budova',
        'Payment' => 'Platba',
        'Invoice' => 'Faktura',
        'Course' => 'Kurz',
        'CourseSeries' => 'Běh kurzu',
        'CourseLesson' => 'Lekce kurzu',
        'CourseEnrollment' => 'Přihláška do kurzu',
        'OneOffEvent' => 'Jednorázová akce',
        'OneOffEventBooking' => 'Přihláška na akci',
        'EventCategory' => 'Kategorie akcí',
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
        'CashReceipt' => 'Pokladní doklad',
        'InvoiceItem' => 'Položka faktury',
        'InvoiceSeries' => 'Číselná řada',
        'Invitation' => 'Pozvánka',
        'SubstituteRule' => 'Pravidlo zastupování',
        'CancellationRule' => 'Pravidlo storna',
        'Setting' => 'Nastavení',
        'RoomBlocking' => 'Blokace ordinace',
        'TherapistWorkBlock' => 'Pracovní blok',
        'TherapistWorkBlockSeries' => 'Série pracovních bloků',
    ];

    /**
     * Grammatical gender of each subject noun (m/f/n), keyed like SUBJECT_LABELS,
     * so create/update/delete verbs in the summary sentence agree with the noun.
     * Defaults to masculine when a type is not listed.
     */
    private const SUBJECT_GENDER = [
        'reservation' => 'f',
        'service' => 'f',
        'course' => 'm',
        'course_series' => 'm',
        'course_enrollment' => 'f',
        'one_off_event' => 'f',
        'one_off_event_booking' => 'f',
        'event_category' => 'f',
        'Reservation' => 'f',
        'User' => 'm',
        'Service' => 'f',
        'ServiceCategory' => 'f',
        'StaffProfile' => 'm',
        'Room' => 'f',
        'Building' => 'f',
        'Payment' => 'f',
        'Invoice' => 'f',
        'Course' => 'm',
        'CourseSeries' => 'm',
        'CourseLesson' => 'f',
        'CourseEnrollment' => 'f',
        'OneOffEvent' => 'f',
        'OneOffEventBooking' => 'f',
        'EventCategory' => 'f',
        'LessonAttendance' => 'f',
        'CreditTransaction' => 'f',
        'CreditAccount' => 'm',
        'GiftVoucher' => 'm',
        'Page' => 'f',
        'Banner' => 'm',
        'Review' => 'f',
        'InstagramConnection' => 'm',
        'EmailTemplate' => 'f',
        'Navigation' => 'f',
        'ClientNote' => 'f',
        'ClientProfile' => 'm',
        'SubstituteToken' => 'm',
        'WaitlistEntry' => 'm',
        'CashReceipt' => 'm',
        'InvoiceItem' => 'f',
        'InvoiceSeries' => 'f',
        'Invitation' => 'f',
        'SubstituteRule' => 'n',
        'CancellationRule' => 'n',
        'Setting' => 'n',
        'RoomBlocking' => 'f',
        'TherapistWorkBlock' => 'm',
        'TherapistWorkBlockSeries' => 'f',
    ];

    /** Past-participle verb forms per event, indexed by gender (m/f/n). */
    private const VERB_FORMS = [
        'created' => ['m' => 'Vytvořen', 'f' => 'Vytvořena', 'n' => 'Vytvořeno'],
        'deleted' => ['m' => 'Smazán', 'f' => 'Smazána', 'n' => 'Smazáno'],
        'restored' => ['m' => 'Obnoven', 'f' => 'Obnovena', 'n' => 'Obnoveno'],
        'updated' => ['m' => 'Upraven', 'f' => 'Upravena', 'n' => 'Upraveno'],
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
        'token' => 'Token',
        'number' => 'Číslo',
        'invoice_number' => 'Číslo faktury',
        'receipt_number' => 'Číslo dokladu',
        'quantity' => 'Množství',
        'unit_price' => 'Jednotková cena',
        'vat_rate' => 'Sazba DPH',
        'cancel_before_hours' => 'Storno lhůta (h)',
        'current_number' => 'Aktuální číslo',
        'prefix' => 'Předpona',
        'key' => 'Klíč',
        'value' => 'Hodnota',
        'work_date' => 'Datum',
        'received_at' => 'Přijato',
        'expires_at' => 'Platnost do',
        'accepted_at' => 'Přijato dne',
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
        'one_off_event_id' => 'Akce',
        'event_category_id' => 'Kategorie',
        'lesson_id' => 'Lekce',
        'enrollment_id' => 'Přihláška',
        'payable_type' => 'Typ předmětu platby',
        'payable_id' => 'Předmět platby',
        'parent_id' => 'Nadřazená položka',
        'navigation_id' => 'Navigace',
        // Semantic-event properties (LogActivity / e-mail listener).
        'recipients' => 'Příjemci',
        'subject' => 'Předmět',
        'notification' => 'Typ zprávy',
        'body_html' => 'Obsah e-mailu',
        'notified_client' => 'Zákazník upozorněn',
        'notified_therapist' => 'Terapeut upozorněn',
        'reason' => 'Důvod',
        'template_key' => 'Šablona',
        'count' => 'Počet záznamů',
        'ids' => 'Dotčené záznamy',
        'records' => 'Dotčené záznamy',
        'source' => 'Zdroj',
        'erased' => 'Přesunuto do koše',
        'fee' => 'Poplatek',
        'due_at' => 'Splatnost',
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

    /**
     * All event keys → labels, for filter option lists.
     *
     * @return array<string, string>
     */
    public static function eventOptions(): array
    {
        return self::EVENT_LABELS;
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
     * A single plain-language Czech sentence describing what happened — the
     * "Log" column. The actor (causer) is shown separately, so this covers only
     * the action and, for edits, the value(s) that changed.
     */
    public static function summary(Activity $activity): string
    {
        $label = self::subjectLabel($activity->subject_type);
        $labelLower = mb_strtolower($label);
        $title = self::subjectTitle($activity);
        $gender = self::subjectGender($activity->subject_type);

        return match ($activity->event) {
            'created' => self::normalize(self::verb('created', $gender).' '.$labelLower.' '.$title),
            'deleted' => self::normalize(self::verb('deleted', $gender).' '.$labelLower.' '.$title),
            'restored' => self::normalize(self::verb('restored', $gender).' '.$labelLower.' '.$title),
            'updated' => self::updatedSummary($activity, $label, $labelLower, $title, $gender),
            'email_sent' => self::emailSummary($activity, $labelLower, $title),
            default => self::normalize(self::eventLabel($activity->event).': '.$labelLower.' '.$title),
        };
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

    /** Summary sentence for an `updated` event, showing what changed. */
    private static function updatedSummary(Activity $activity, string $label, string $labelLower, string $title, string $gender): string
    {
        $fields = self::changedFields($activity);

        if ($fields === []) {
            return self::normalize(self::verb('updated', $gender).' '.$labelLower.' '.$title);
        }

        if (count($fields) === 1) {
            $key = array_key_first($fields);
            [$old, $new] = $fields[$key];

            return self::normalize($label.' '.$title).': '.self::attributeLabel($key)
                .' „'.self::formatValue($activity, $key, $old).'" → „'.self::formatValue($activity, $key, $new).'"';
        }

        $labels = array_map(fn (string $key): string => self::attributeLabel($key), array_keys($fields));
        $shown = array_slice($labels, 0, 4);
        $rest = count($labels) - count($shown);
        $list = implode(', ', $shown).($rest > 0 ? ' +'.$rest.' dalších' : '');

        return self::normalize(self::verb('updated', $gender).' '.$labelLower.' '.$title).': '.$list;
    }

    /** Summary sentence for a logged e-mail, preferring the subject line and first recipient. */
    private static function emailSummary(Activity $activity, string $labelLower, string $title): string
    {
        $subject = $activity->getProperty('subject');
        $recipients = $activity->getProperty('recipients') ?? [];
        $first = is_array($recipients) ? ($recipients[0] ?? null) : $recipients;

        if (is_string($subject) && $subject !== '') {
            $line = 'Odeslán e-mail „'.Str::limit($subject, 60).'"';

            if (is_string($first) && $first !== '') {
                $line .= ' – '.self::cleanRecipient($first);
            }

            return $line;
        }

        return self::normalize('E-mail odeslán – '.$labelLower.' '.$title);
    }

    /**
     * Changed attributes for an `updated` event as key => [old, new], excluding
     * pure-timestamp churn (matches what the diff view treats as the change set).
     *
     * @return array<string, array{mixed, mixed}>
     */
    private static function changedFields(Activity $activity): array
    {
        $new = $activity->attribute_changes['attributes'] ?? [];
        $old = $activity->attribute_changes['old'] ?? [];
        $ignore = ['updated_at', 'created_at'];

        $result = [];

        foreach (array_keys($new + $old) as $key) {
            if (in_array($key, $ignore, true)) {
                continue;
            }

            $result[$key] = [$old[$key] ?? null, $new[$key] ?? null];
        }

        return $result;
    }

    /**
     * A short, human-readable rendering of a stored attribute value: booleans as
     * Ano/Ne, labelled enums resolved to their Czech label, empties as „prázdné".
     */
    private static function formatValue(Activity $activity, string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'prázdné';
        }

        if (is_bool($value)) {
            return $value ? 'Ano' : 'Ne';
        }

        // Best-effort: render a backed enum via its label ("confirmed" → "Potvrzeno").
        $subject = $activity->subject;

        if ($subject !== null) {
            $cast = $subject->getCasts()[$key] ?? null;

            if (is_string($cast) && enum_exists($cast) && method_exists($cast, 'tryFrom')) {
                try {
                    $enum = $cast::tryFrom($value);

                    if ($enum instanceof HasLabel) {
                        return (string) $enum->getLabel();
                    }

                    if ($enum !== null && method_exists($enum, 'label')) {
                        return (string) $enum->label();
                    }
                } catch (Throwable) {
                    // Fall through to the raw string rendering.
                }
            }
        }

        $string = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);

        return Str::limit($string, 40);
    }

    /** The display name of a recipient, dropping the `<email>` part when a name is present. */
    private static function cleanRecipient(string $recipient): string
    {
        if (str_contains($recipient, '<')) {
            $name = trim(explode('<', $recipient)[0]);

            return $name !== '' ? $name : trim(explode('<', $recipient)[1], '> ');
        }

        return $recipient;
    }

    private static function subjectGender(?string $subjectType): string
    {
        if ($subjectType === null) {
            return 'm';
        }

        return self::SUBJECT_GENDER[$subjectType]
            ?? self::SUBJECT_GENDER[class_basename($subjectType)]
            ?? 'm';
    }

    private static function verb(string $event, string $gender): string
    {
        return self::VERB_FORMS[$event][$gender] ?? self::VERB_FORMS[$event]['m'];
    }

    /** Collapse doubled/edge whitespace left by an empty title or label. */
    private static function normalize(string $sentence): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sentence));
    }
}
