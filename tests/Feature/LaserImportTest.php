<?php

namespace Tests\Feature;

use App\Console\Commands\LaserImport;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LaserImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-22T08:00:00+02:00');
        Notification::fake();

        Service::factory()->create(['slug' => 'laserova-terapie', 'name' => 'Laserová terapie']);

        User::factory()->therapist()->create(['email' => 'denisa.novakova@friendlyfyzio.cz', 'name' => 'Denisa Nováková']);
        User::factory()->therapist()->create(['email' => 'ema.murcova@friendlyfyzio.cz', 'name' => 'Ema Murčová']);

        User::factory()->customer()->create(['name' => 'Existing Client', 'email' => 'existing.client@example.com']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function runImport(bool $dryRun = false): void
    {
        $this->artisan('laser:import', array_filter([
            'path' => 'tests/Fixtures/googlecalendar/laser-kryo.json',
            '--dry-run' => $dryRun,
        ]))->assertSuccessful();
    }

    protected function profileFor(string $email): StaffProfile
    {
        return User::query()->where('email', $email)->firstOrFail()->staffProfile;
    }

    public function test_creates_one_laser_reservation_per_future_event(): void
    {
        $this->runImport();

        // 5 future events → 5 reservations; the past event is skipped.
        $this->assertSame(5, Reservation::query()->count());

        $reservation = Reservation::query()->firstOrFail();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame(PaymentStatus::Unpaid, $reservation->payment_status);
        $this->assertSame('laserova-terapie', $reservation->service->slug);
        $this->assertSame('Laser', $reservation->room->name);
        $this->assertNotNull($reservation->imported_at);
        // Pre-armed reminder so the reminder sweep never e-mails these.
        $this->assertNotNull($reservation->reminder_sent_at);
    }

    public function test_derives_therapist_from_operator_default_and_tag(): void
    {
        $this->runImport();

        $denisa = $this->profileFor('denisa.novakova@friendlyfyzio.cz');
        $ema = $this->profileFor('ema.murcova@friendlyfyzio.cz');

        // "Anet … - EMA" → Ema; everything else defaults to the operator Denisa.
        $anet = Reservation::query()->whereHas('client', fn ($q) => $q->where('name', 'Anet Župníková'))->firstOrFail();
        $this->assertSame($ema->getKey(), $anet->therapist_id);

        $this->assertSame(4, Reservation::query()->where('therapist_id', $denisa->getKey())->count());
    }

    public function test_empty_operator_slot_uses_the_no_client_placeholder(): void
    {
        $this->runImport();

        $noClient = User::query()->where('email', 'laser-bez-klienta@friendlyfyzio.cz')->firstOrFail();
        $this->assertFalse($noClient->isCustomer()); // kept out of Klienti
        $this->assertSame(1, Reservation::query()->where('client_id', $noClient->getKey())->count());
    }

    public function test_matches_existing_clients_and_creates_placeholders_for_new_ones(): void
    {
        $this->runImport();

        // Matched by name — no duplicate account.
        $existing = User::query()->where('email', 'existing.client@example.com')->firstOrFail();
        $this->assertSame(1, User::query()->where('name', 'Existing Client')->count());
        $this->assertTrue(Reservation::query()->where('client_id', $existing->getKey())->exists());

        // Unknown name → tagged placeholder customer.
        $sara = User::query()->where('name', 'Sára Slezáková')->firstOrFail();
        $this->assertTrue($sara->isCustomer());
        $this->assertTrue($sara->tags->pluck('name')->contains(LaserImport::IMPORT_TAG));
    }

    public function test_is_idempotent_and_sends_no_mail(): void
    {
        $this->runImport();
        $this->runImport();

        $this->assertSame(5, Reservation::query()->count());
        $this->assertSame(1, User::query()->where('name', 'Sára Slezáková')->count());

        Notification::assertNothingSent();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->runImport(dryRun: true);

        $this->assertSame(0, Reservation::query()->count());
        $this->assertSame(0, Room::query()->where('name', 'Laser')->count());
    }
}
