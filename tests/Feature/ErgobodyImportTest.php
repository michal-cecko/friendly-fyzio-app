<?php

namespace Tests\Feature;

use App\Console\Commands\ErgobodyImport;
use App\Enums\Gender;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\ClientNote;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ErgobodyImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $lucie;

    protected User $sarka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lucie = User::factory()->admin()->therapist()->create([
            'name' => 'Lucie Fickerová',
            'email' => 'lucie.fickerova@friendlyfyzio.cz',
        ]);

        $this->sarka = User::factory()->therapist()->create([
            'name' => 'Šárka Antošíková',
            'email' => 'sarka.antosikova@friendlyfyzio.cz',
        ]);
    }

    protected function runImport(bool $dryRun = false): void
    {
        $this->artisan('ergobody:import', array_filter([
            'path' => 'tests/Fixtures/ergobody',
            '--dry-run' => $dryRun,
        ]))->assertSuccessful();
    }

    public function test_imports_clients_with_mapped_profile_fields(): void
    {
        $this->runImport();

        $barbora = User::query()->where('email', 'barbora@example.com')->firstOrFail();

        $this->assertSame('Barbora Testová', $barbora->name);
        $this->assertSame('+420720936876', $barbora->phone);
        $this->assertTrue($barbora->isCustomer());
        $this->assertNull($barbora->email_verified_at);
        $this->assertSame('2024-01-02', $barbora->created_at->toDateString());
        $this->assertTrue($barbora->tags->pluck('name')->contains(ErgobodyImport::IMPORT_TAG));

        $profile = $barbora->clientProfile;
        $this->assertSame(Gender::Female, $profile->gender);
        $this->assertSame('905728/5963', $profile->birth_number);
        $this->assertSame('1981-11-13', $profile->date_of_birth->toDateString());
        $this->assertSame('Ostrava', $profile->address_city);
        $this->assertSame('sedavé', $profile->occupation);
        $this->assertSame('60.00', $profile->weight);
        $this->assertSame('166.00', $profile->height);
        $this->assertStringContainsString('Alergie na náplast', (string) $profile->anamnesis);
    }

    public function test_rewrites_placeholder_emails_and_nulls_zero_measurements(): void
    {
        $this->runImport();

        $tereza = User::query()->where('email', 'import+3023@friendlyfyzio.cz')->firstOrFail();

        $this->assertSame('Tereza Nováčková', $tereza->name);
        $this->assertSame('+420732482593', $tereza->phone);
        $this->assertNull($tereza->clientProfile->weight);
        $this->assertNull($tereza->clientProfile->height);
        $this->assertNull($tereza->clientProfile->birth_number);
        $this->assertSame(Gender::Female, $tereza->clientProfile->gender);
    }

    public function test_staff_card_attaches_to_the_existing_staff_account(): void
    {
        $this->runImport();

        // Lucie is treated here herself: her patient card and notes attach to
        // her staff account rather than creating a second person.
        $this->assertSame(1, User::query()->where('name', 'Lucie Fickerová')->count());

        $lucie = $this->lucie->fresh();
        $this->assertTrue($lucie->isAdmin());
        $this->assertSame('lucie.fickerova@friendlyfyzio.cz', $lucie->email, 'Her login e-mail is untouched.');
        $this->assertSame('fyzioterapeut', $lucie->clientProfile->occupation);
        $this->assertSame('65.00', $lucie->clientProfile->weight);
        $this->assertSame(1, $lucie->clientNotes()->count());
    }

    public function test_creates_deactivated_former_therapists(): void
    {
        $this->runImport();

        $former = User::query()->where('email', 'renata.dojcsanova@friendlyfyzio.cz')->firstOrFail();
        $this->assertTrue($former->isTherapist());
        $this->assertNotNull($former->deactivated_at);

        // She gets a profile only so her historical visits have somewhere to
        // hang; it must stay unpublished and carry no bookable service.
        $this->assertNotNull($former->staffProfile);
        $this->assertNull($former->staffProfile->published_at);
        $this->assertSame(0, $former->staffProfile->services()->count());
    }

    public function test_merges_duplicate_cards_of_the_same_person(): void
    {
        $this->runImport();

        // Barbora has two cards sharing a phone number; the second has no birth
        // date, so only phone matching can tell they are one person.
        $this->assertSame(1, User::query()->where('name', 'like', 'Barbora%')->count());
        $this->assertNull(User::query()->where('email', 'barbora.druha@example.com')->first());

        $barbora = User::query()->where('email', 'barbora@example.com')->firstOrFail();
        $this->assertSame('2024-01-02', $barbora->created_at->toDateString(), 'Earliest card date wins.');
        $this->assertSame('sedavé', $barbora->clientProfile->occupation, 'Richer card wins on conflicts.');

        // The note filed against the second card lands on the merged client.
        $this->assertSame(
            1,
            $barbora->clientNotes()->where('content', 'like', '%druhou kartu%')->count(),
        );
    }

    public function test_keeps_genuine_namesakes_apart_and_splits_their_notes_by_date(): void
    {
        $this->runImport();

        $older = User::query()->where('email', 'jana.starsi@example.com')->firstOrFail();
        $younger = User::query()->where('email', 'jana.mladsi@example.com')->firstOrFail();

        $this->assertNotSame($older->getKey(), $younger->getKey());
        $this->assertSame(1, $younger->clientNotes()->count());
        $this->assertStringContainsString('mladší Jany', $younger->clientNotes()->first()->content);
        $this->assertSame(
            1,
            $older->clientNotes()->where('content', 'like', '%starší Jany%')->count(),
        );
        $this->assertSame(
            0,
            $older->clientNotes()->where('content', 'like', '%mladší Jany%')->count(),
        );
    }

    public function test_resolves_overlapping_namesakes_via_content_overrides(): void
    {
        $this->runImport();

        // Both Lenkas were in treatment on 24. 2. 2025, so only the override
        // (Caesarean + maternity leave) can place that note.
        $older = User::query()->where('email', 'lenka.skanderova@centrum.cz')->firstOrFail();
        $younger = User::query()->where('email', 'lenka.skanderova@vsb.cz')->firstOrFail();

        $this->assertStringContainsString('halux valgus', $older->clientNotes()->first()->content);
        $this->assertStringContainsString('císařském řezu', $younger->clientNotes()->first()->content);
    }

    public function test_resolves_abbreviated_and_misspelled_signatures(): void
    {
        $denisa = User::factory()->therapist()->create([
            'name' => 'Denisa Nováková',
            'email' => 'denisa.novakova@friendlyfyzio.cz',
        ]);
        $michaela = User::factory()->therapist()->create([
            'name' => 'Michaela Hrubá',
            'email' => 'michaela.hruba@friendlyfyzio.cz',
        ]);
        $ema = User::factory()->therapist()->create([
            'name' => 'Ema Murčová',
            'email' => 'ema.murcova@friendlyfyzio.cz',
        ]);

        $this->runImport();

        $notes = User::query()->where('email', 'jana.starsi@example.com')->firstOrFail()
            ->clientNotes()
            ->orderBy('created_at')
            ->get()
            ->keyBy(fn ($note): string => $note->created_at->toDateString());

        $this->assertSame($denisa->getKey(), $notes['2024-03-20']->author_id, 'First name only.');
        $this->assertSame($michaela->getKey(), $notes['2024-03-21']->author_id, 'Initials.');
        $this->assertSame($this->lucie->getKey(), $notes['2024-03-22']->author_id, 'Surname only.');
        $this->assertSame($ema->getKey(), $notes['2024-03-23']->author_id, 'Misspelled surname.');
        $this->assertNull(
            $notes['2024-03-24']->author_id,
            'A long trailing sentence is note text, never a signature.',
        );
    }

    public function test_newest_anamnesis_fills_the_profile_and_older_ones_stay_as_notes(): void
    {
        $this->runImport();

        $barbora = User::query()->where('email', 'barbora@example.com')->firstOrFail();

        // Anamnesis describes the client, so the newest entry is the profile's.
        $this->assertStringContainsString('Novější anamnéza po roce', $barbora->clientProfile->anamnesis);
        $this->assertStringContainsString('Anamnéza z 10. 6. 2025', $barbora->clientProfile->anamnesis);
        $this->assertStringNotContainsString(
            'Gynekologická',
            $barbora->clientProfile->anamnesis,
            'The superseded entry must not linger in the field.',
        );

        // The earlier one is kept as history, pain entries included.
        $archived = $barbora->clientNotes()->where('content', 'like', '%Gynekologická%')->firstOrFail();
        $this->assertSame('2024-01-02', $archived->created_at->toDateString());
        $this->assertStringContainsString('Anamnéza z 2. 1. 2024', $archived->content);
        $this->assertStringContainsString('Bolest: kostrč, tupá, intenzita 7 — při dlouhém sedu', $archived->content);

        // A client with a single anamnesis gets it on the profile and no note.
        $jana = User::query()->where('email', 'jana.starsi@example.com')->firstOrFail();
        $this->assertStringContainsString('Anamnéza starší Jany', $jana->clientProfile->anamnesis);
        $this->assertSame(0, $jana->clientNotes()->where('content', 'like', '%Anamnéza starší%')->count());
    }

    public function test_imports_notes_with_authors_and_dates(): void
    {
        $this->runImport();

        $barbora = User::query()->where('email', 'barbora@example.com')->firstOrFail();
        $notes = ClientNote::query()
            ->where('client_id', $barbora->getKey())
            ->where('content', 'not like', '%Anamnéza%')
            ->orderBy('created_at')
            ->get();

        $this->assertCount(4, $notes);

        [$first, $second, $fourth, $merged] = $notes;

        $this->assertSame(
            $this->sarka->getKey(),
            $merged->author_id,
            'The misspelled signature "Šárka Antošíkvá" still resolves to Šárka.',
        );

        $this->assertSame('2024-01-02', $first->created_at->toDateString());
        $this->assertSame($this->lucie->getKey(), $first->author_id, 'Alias "Lucka Fickerová" resolves to Lucie.');
        $this->assertStringContainsString('korekce dřepu', $first->content);

        $this->assertNotEquals($first->created_at, $second->created_at, 'Same-day notes get distinct timestamps.');
        $this->assertSame(
            User::query()->where('email', 'renata.dojcsanova@friendlyfyzio.cz')->value('id'),
            $second->author_id,
        );
        $this->assertStringContainsString('Doporučení ke specialistovi: ortoped', $second->content);

        $this->assertSame('2024-03-07', $fourth->created_at->toDateString());
        $this->assertNull($fourth->author_id, 'Unknown signature stays unattributed.');

        // A note for someone with no Kartotéka card is imported for nobody.
        $this->assertSame(0, ClientNote::query()->where('content', 'like', '%terapie ramene%')->count());
    }

    public function test_rebuilds_historical_reservations_from_therapy_notes(): void
    {
        $this->runImport();

        $barbora = User::query()->where('email', 'barbora@example.com')->firstOrFail();
        $note = $barbora->clientNotes()->where('content', 'like', '%korekce dřepu%')->firstOrFail();

        $reservation = $note->reservation;
        $this->assertNotNull($reservation, 'The note is linked to the visit it describes.');
        $this->assertSame('2024-01-02', $reservation->reservation_date->toDateString());
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame(PaymentStatus::Paid, $reservation->payment_status);
        $this->assertNotNull($reservation->settled_at, 'Nothing is left to chase on a historical visit.');
        $this->assertNotNull($reservation->imported_at);
        $this->assertNull($reservation->room_id, 'The room is genuinely unknown.');
        $this->assertSame(0, $reservation->service->price, 'No revenue is invented.');
        $this->assertSame(0, $reservation->payments()->count(), 'No payment records are invented.');
        $this->assertSame($this->lucie->staffProfile->getKey(), $reservation->therapist_id);
    }

    public function test_same_day_visits_get_distinct_non_conflicting_slots(): void
    {
        $this->runImport();

        // Both 2. 1. 2024 notes were written by different therapists, so build
        // the collision case explicitly: Lucie's two visits on one day.
        $slots = Reservation::query()
            ->whereNotNull('imported_at')
            ->get()
            ->groupBy(fn (Reservation $r): string => $r->therapist_id.'|'.$r->reservation_date->toDateString());

        foreach ($slots as $key => $group) {
            $times = $group->pluck('start_time')->map(fn ($t): string => (string) $t);
            $this->assertSame(
                $times->unique()->count(),
                $times->count(),
                "Therapist/day {$key} has two visits sharing a start time.",
            );
        }

        $this->assertGreaterThan(0, Reservation::query()->whereNotNull('imported_at')->count());
    }

    public function test_notes_without_a_therapist_produce_no_reservation(): void
    {
        $this->runImport();

        $orphan = ClientNote::query()
            ->where('content', 'like', '%Jarmila Neznámá%')
            ->firstOrFail();

        $this->assertNull($orphan->author_id);
        $this->assertNull($orphan->reservation_id, 'A visit is never invented under the wrong therapist.');
    }

    public function test_anamnesis_notes_never_become_visits(): void
    {
        $this->runImport();

        $archived = ClientNote::query()->where('content', 'like', '%Gynekologická%')->firstOrFail();

        $this->assertNull($archived->reservation_id, 'An anamnesis is a record, not an appointment.');
    }

    public function test_historical_reservations_trigger_no_email(): void
    {
        Notification::fake();

        $this->runImport();

        $this->assertGreaterThan(0, Reservation::query()->whereNotNull('imported_at')->count());

        foreach ([
            'reservations:send-confirmations',
            'reservations:send-reminders',
            'reservations:cancel-unconfirmed',
            'payments:mark-overdue',
            'reviews:send-requests',
            'reservations:settle-past',
        ] as $command) {
            $this->artisan($command)->assertSuccessful();
        }

        Notification::assertNothingSent();
    }

    public function test_imported_visits_stay_out_of_the_calendar(): void
    {
        $this->runImport();

        $imported = Reservation::query()->whereNotNull('imported_at')->firstOrFail();

        // A normally booked visit on the same day proves the calendar really is
        // rendering that range, so the exclusion below isn't a vacuous pass.
        $booked = Reservation::factory()->create([
            'reservation_date' => $imported->reservation_date,
            'start_time' => '15:00:00',
            'end_time' => '15:30:00',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        $events = Livewire::test(ReservationCalendar::class)
            ->call('fetchEvents', [
                'start' => $imported->reservation_date->copy()->subDay()->toDateString(),
                'end' => $imported->reservation_date->copy()->addDays(2)->toDateString(),
            ])
            ->effects['returns'][0] ?? [];

        $ids = collect($events)->pluck('id')->all();

        $this->assertContains($booked->getKey(), $ids, 'Real bookings still render.');
        $this->assertNotContains(
            $imported->getKey(),
            $ids,
            'Placeholder times must not be drawn on the calendar as if they were real.',
        );
    }

    public function test_is_idempotent_and_does_not_write_activity_log(): void
    {
        $this->runImport();

        $users = User::query()->count();
        $notes = ClientNote::query()->count();
        $activities = Activity::query()->count();

        $this->runImport();

        $this->assertSame($users, User::query()->count());
        $this->assertSame($notes, ClientNote::query()->count());
        $this->assertSame($activities, Activity::query()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $usersBefore = User::query()->count();

        $this->runImport(dryRun: true);

        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame(0, ClientNote::query()->count());
    }

    public function test_import_activity_is_not_logged(): void
    {
        $before = Activity::query()->count();

        $this->runImport();

        $this->assertSame($before, Activity::query()->count());
    }
}
