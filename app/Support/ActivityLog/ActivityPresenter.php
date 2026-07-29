<?php

namespace App\Support\ActivityLog;

use App\Models\Lesson;
use App\Models\LessonAttendance;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

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
        'reservation_doctor_note_uploaded' => 'Potvrzení od lékaře nahráno',
        'reservation_auto_cancelled' => 'Automaticky zrušeno',
        'reservation_completed' => 'Rezervace vybavena',
        'reservation_reactivated' => 'Rezervace obnovena',
        'account_deactivated' => 'Účet deaktivován',
        'payment_requested' => 'Platba vyžádána',
        'payment_received' => 'Platba přijata',
        'invoice_issued' => 'Faktura vystavena',
        'bulk_deleted' => 'Hromadné smazání',
        'lesson_absence' => 'Odhlášen z lekce',
        'lesson_absence_reverted' => 'Vrácen do lekce',
        'lesson_attendance_recorded' => 'Účast zaznamenána',
        'review_published' => 'Recenze zveřejněna',
        'review_hidden' => 'Recenze skryta',
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
        'reservation_doctor_note_uploaded' => 'info',
        'reservation_auto_cancelled' => 'danger',
        'reservation_completed' => 'gray',
        'reservation_reactivated' => 'success',
        'account_deactivated' => 'danger',
        'payment_requested' => 'info',
        'payment_received' => 'success',
        'invoice_issued' => 'info',
        'bulk_deleted' => 'danger',
        'lesson_absence' => 'warning',
        'lesson_absence_reverted' => 'success',
        'lesson_attendance_recorded' => 'success',
        'review_published' => 'success',
        'review_hidden' => 'gray',
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
        'lesson' => 'Lekce',
        'lesson_booking' => 'Přihláška na akci',
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
        'CourseEnrollment' => 'Přihláška do kurzu',
        'Lesson' => 'Lekce',
        'LessonBooking' => 'Přihláška na akci',
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
        'lesson' => 'f',
        'lesson_booking' => 'f',
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
        'CourseEnrollment' => 'f',
        'Lesson' => 'f',
        'LessonBooking' => 'f',
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
        'lesson_id' => 'Akce',
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
        // Structured values unpacked by ActivityValue: billing snapshots, settings
        // config, therapist profile columns. Keys that a Filament schema already
        // names (brick config, repeater rows) are deliberately absent — they are
        // resolved from that schema instead, see FieldLabels.
        'bio' => 'Medailonek',
        'client_snapshot' => 'Odběratel',
        'supplier_snapshot' => 'Dodavatel',
        'config' => 'Konfigurace',
        'format' => 'Formát',
        'preferences' => 'Předvolby',
        'ico' => 'IČO',
        'dic' => 'DIČ',
        'iban' => 'IBAN',
        'bank_account' => 'Číslo účtu',
        'vat_payer' => 'Plátce DPH',
        'registration' => 'Registrace',
        'heading' => 'Nadpis',
        'badge_text' => 'Text odznaku',
        'cta_text' => 'Text tlačítka',
        'cta_url' => 'Odkaz tlačítka',
        'background' => 'Pozadí',
        'icon' => 'Ikona',
        'items' => 'Položky',
        'buttons' => 'Tlačítka',
        'min' => 'Minimum',
        'max' => 'Maximum',
        'step' => 'Krok',
        'suffix' => 'Přípona',
        'type' => 'Typ',
        // System columns no form declares: lifecycle timestamps, morph keys,
        // internal flags. Everything a Filament form does name is resolved from
        // that form instead (see FieldLabels).
        'id' => 'ID',
        'group' => 'Skupina',
        'birth_number' => 'Rodné číslo',
        'waitlist_promotion_mode' => 'Uvolněné místo z pořadníku',
        'waitlist_invited_until' => 'Místo drženo čekajícím do',
        'reservations' => 'Rezervace',
        'enrollments' => 'Přihlášky na kurz',
        'bookings' => 'Přihlášky na lekci',
        'waitlist' => 'Místa v pořadníku',
        'show_stats' => 'Zobrazovat statistiky',
        'sort' => 'Pořadí',
        'total' => 'Celkem',
        'gender' => 'Pohlaví',
        'day_of_week' => 'Den v týdnu',
        'week_type' => 'Typ týdne',
        'is_recurring' => 'Opakující se',
        'system_key' => 'Systémový klíč',
        'last_reset_year' => 'Poslední reset (rok)',
        'title_before' => 'Titul před jménem',
        'title_after' => 'Titul za jménem',
        'created_by' => 'Vytvořil',
        'featured_image' => 'Hlavní obrázek',
        'detail_image' => 'Fotka do detailu',
        'footer_note' => 'Poznámka v patičce',
        'vat_note' => 'Poznámka k DPH',
        'invoice_title' => 'Název pro fakturaci',
        'text_before_items' => 'Text nad položkami',
        'text_after_items' => 'Text pod položkami',
        'variable_symbol' => 'Variabilní symbol',
        'payment_method' => 'Způsob platby',
        'payment_status' => 'Stav platby',
        'payable_label' => 'Předmět platby',
        'payment_id' => 'Platba',
        'invoice_id' => 'Faktura',
        'presale_token' => 'Token předprodeje',
        'cancellation_reason' => 'Důvod zrušení',
        'source_lesson_id' => 'Zdrojová lekce',
        'used_for_lesson_id' => 'Použito na lekci',
        'target_series_id' => 'Cílový běh kurzu',
        'related_transaction_id' => 'Související transakce',
        'invoiceable_id' => 'Předmět faktury',
        'invoiceable_type' => 'Typ předmětu faktury',
        'pageable_id' => 'Navázaný záznam',
        'pageable_type' => 'Typ navázaného záznamu',
        'waitlistable_id' => 'Předmět pořadníku',
        'waitlistable_type' => 'Typ předmětu pořadníku',
        'lesson_date' => 'Datum akce',
        'start_at' => 'Začátek',
        'end_at' => 'Konec',
        'starts_on' => 'Platí od',
        'ends_on' => 'Platí do',
        'issued_at' => 'Vystaveno',
        'imported_at' => 'Importováno',
        'used_at' => 'Použito',
        'settled_at' => 'Vybaveno dne',
        'confirmed_at' => 'Potvrzeno dne',
        'confirmed_by' => 'Potvrzeno kým',
        'confirmed_by_id' => 'Potvrdil',
        'confirmation_sent_at' => 'Potvrzení odesláno',
        'reactivated_at' => 'Obnoveno dne',
        'deactivated_at' => 'Deaktivováno',
        'generated_until' => 'Vygenerováno do',
        'notified_at' => 'Upozorněno',
        'reminder_sent_at' => 'Připomínka odeslána',
        'overdue_notified_at' => 'Upomínka odeslána',
        'expiry_notified_at' => 'Upozornění na expiraci',
        'newsletter_opted_in_at' => 'Přihlášení k newsletteru',
        'doctor_note_requested_at' => 'Potvrzení od lékaře vyžádáno',
        'doctor_note_resolved_at' => 'Potvrzení od lékaře vyřešeno',
        'count' => 'Počet záznamů',
        'ids' => 'Dotčené záznamy',
        'records' => 'Dotčené záznamy',
        'source' => 'Zdroj',
        'erased' => 'Přesunuto do koše',
        'fee' => 'Poplatek',
        'file' => 'Soubor',
        'due_at' => 'Splatnost',
        'client' => 'Klient',
        'cc' => 'Kopie',
        'bcc' => 'Skrytá kopie',
        'notified' => 'Zákazník upozorněn',
        'past' => 'Lekce už proběhla',
        'override' => 'Poukaz nad rámec pravidel',
        'substitute_token' => 'Poukaz na náhradu',
        'substitute_token_withdrawn' => 'Poukaz na náhradu odebrán',
        'token_generated' => 'Poukaz vygenerován',
        'auto_promote_waitlist' => 'Automaticky posouvat z pořadníku',
        'excuse_reason' => 'Důvod omluvy',
        'excuse_note' => 'Poznámka k omluvě',
        'excused_by_id' => 'Omluvil',
        'replacement_attendance_id' => 'Náhradní lekce',
        'source_attendance_id' => 'Původní lekce',
        'max_substitutions' => 'Maximální počet náhrad',
        'early_cancel_hours' => 'Včasné odhlášení (hodin předem)',
        'duration_minutes' => 'Délka (minut)',
        'break_minutes' => 'Pauza po termínu (minut)',
        'break_blocks' => 'Pauza po termínu (bloků)',
        'existing_client_months' => 'Kontrolní vyšetření do (měsíců)',
        'is_control_therapy' => 'Kontrolní vyšetření',
        'author_id' => 'Autor',
        'author_name' => 'Jméno autora',
        'reservation_id' => 'Rezervace',
        'visible' => 'Viditelné',
        'reviewable_id' => 'Předmět recenze',
        'reviewable_type' => 'Typ předmětu recenze',
        'sort_order' => 'Pořadí',
        'short_name' => 'Zkratka',
        'placement' => 'Umístění',
        'page_ids' => 'Stránky',
        'active_from' => 'Aktivní od',
        'active_to' => 'Aktivní do',
        'hero_image' => 'Hlavní obrázek',
        'meta_title' => 'Meta titulek',
        'meta_description' => 'Meta popis',
        'is_system' => 'Systémový záznam',
        'is_default' => 'Výchozí',
        'document_type' => 'Typ dokladu',
        'reset_yearly' => 'Reset číslování každý rok',
        'padding' => 'Počet číslic',
        'purpose' => 'Účel',
        'received_by' => 'Přijal',
        'client_name' => 'Jméno klienta',
        'recipient_name' => 'Jméno obdarovaného',
        'recipient_email' => 'E-mail obdarovaného',
        'voucher_code' => 'Kód poukazu',
        'purchased_at' => 'Zakoupeno',
        'redeemed_at' => 'Uplatněno',
        'credited_to_client_id' => 'Kredit připsán klientovi',
        'invited_by' => 'Pozval',
        'inviteable_id' => 'Předmět pozvánky',
        'inviteable_type' => 'Typ předmětu pozvánky',
        'token' => 'Token',
        'access_token' => 'Přístupový token',
        'token_expires_at' => 'Platnost tokenu do',
        'username' => 'Uživatelské jméno',
        'instagram_user_id' => 'Instagram ID',
        'last_synced_at' => 'Poslední synchronizace',
        'last_error' => 'Poslední chyba',
    ];

    /**
     * A human-readable Czech label for a changed attribute key. Model columns
     * come from the table above; nested keys inside a Mason brick, repeater or
     * builder are named by the schema that declares them ({@see FieldLabels}),
     * with an optional scope to disambiguate same-named fields across bricks.
     *
     * @param  array<string, string>  $scope  Field → label for the structure the key belongs to.
     */
    public static function attributeLabel(string $key, array $scope = []): string
    {
        return $scope[$key]
            ?? self::ATTRIBUTE_LABELS[$key]
            ?? FieldLabels::all()[$key]
            ?? ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $key) ?? $key));
    }

    /**
     * The label scope for an entry's own record — the Czech names its Filament
     * resource form gives its columns. Pass this into {@see attributeLabel()}
     * and {@see ActivityValue} when rendering that entry.
     *
     * @return array<string, string>
     */
    public static function attributeScope(Activity $activity): array
    {
        return FieldLabels::forModel($activity->subject_type);
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
            'lesson_absence',
            'lesson_absence_reverted',
            'lesson_attendance_recorded' => self::lessonAttendanceSummary($activity, $labelLower, $title),
            // The event label already names the subject; repeating it would read
            // as "Recenze zveřejněna: recenze …".
            'review_published',
            'review_hidden' => self::normalize(self::eventLabel($activity->event).': '.$title),
            default => self::normalize(self::eventLabel($activity->event).': '.$labelLower.' '.$title),
        };
    }

    /**
     * The presence events are about a person, not the lesson, so the client's
     * name leads and the lesson follows as the place it happened. Rows logged
     * before the name was recorded fall back to the plain sentence.
     */
    private static function lessonAttendanceSummary(Activity $activity, string $labelLower, string $title): string
    {
        $client = $activity->getProperty('client');
        $event = self::eventLabel($activity->event);
        $place = self::lessonPlace($activity) ?? $labelLower.' '.$title;

        if (! is_string($client) || $client === '') {
            return self::normalize($event.': '.$place);
        }

        return self::normalize($event.': '.$client.' — '.$place);
    }

    /**
     * The lesson a presence event happened on. These used to be filed against
     * the lesson itself and are now filed against the seat they changed, so the
     * name is read from whichever of the two the row points at.
     */
    private static function lessonPlace(Activity $activity): ?string
    {
        $subject = $activity->subject;

        if ($subject instanceof Lesson) {
            return $subject->logTitle();
        }

        $lesson = $subject instanceof LessonAttendance
            ? $subject->lesson
            : Lesson::query()->find($activity->getProperty('lesson_id'));

        return $lesson?->logTitle();
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

        return $subject === null ? null : ActivityLink::url($subject);
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
        $scope = self::attributeScope($activity);

        if ($fields === []) {
            return self::normalize(self::verb('updated', $gender).' '.$labelLower.' '.$title);
        }

        if (count($fields) === 1) {
            $key = array_key_first($fields);
            [$old, $new] = $fields[$key];
            $before = self::formatValue($activity, $key, $old);
            $after = self::formatValue($activity, $key, $new);
            $prefix = self::normalize($label.' '.$title).': '.self::attributeLabel($key, $scope);

            // Structures (page content, snapshots) can summarise identically even
            // though something inside them moved — point at the diff instead of
            // showing the same text twice.
            return $before === $after
                ? $prefix.' – změněno'
                : $prefix.' „'.$before.'" → „'.$after.'"';
        }

        $labels = array_map(fn (string $key): string => self::attributeLabel($key, $scope), array_keys($fields));
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
     * A short, human-readable rendering of a stored attribute value — see
     * {@see ActivityValue} for how structures and rich text are unpacked.
     */
    private static function formatValue(Activity $activity, string $key, mixed $value): string
    {
        return ActivityValue::inline($value, $key, $activity->subject, limit: 60);
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
