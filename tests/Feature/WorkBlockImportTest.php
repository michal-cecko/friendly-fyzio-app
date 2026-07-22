<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkBlockImportTest extends TestCase
{
    use RefreshDatabase;

    protected Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic "today" so past/future skipping is stable.
        Carbon::setTestNow('2026-07-22T08:00:00+02:00');

        $this->building = Building::factory()->create();
        $this->room('Ambulance velká');
        $this->room('Ambulance malá');

        foreach ([
            'ema.murcova@friendlyfyzio.cz' => 'Ema Murčová',
            'denisa.novakova@friendlyfyzio.cz' => 'Denisa Nováková',
            'renata.prnka@friendlyfyzio.cz' => 'Renáta Prnka',
            'sarka.antosikova@friendlyfyzio.cz' => 'Šárka Antošíková',
            'lucie.fickerova@friendlyfyzio.cz' => 'Lucie Fickerová',
            'daniela.steblova@friendlyfyzio.cz' => 'Daniela Steblová',
            'lada.cincilova@friendlyfyzio.cz' => 'Lada Činčilová',
        ] as $email => $name) {
            // therapist() grants the Therapist capability, which auto-creates the
            // (unpublished) staff profile the work block's therapist_id points to.
            User::factory()->therapist()->create(['email' => $email, 'name' => $name]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function room(string $name): Room
    {
        return Room::factory()->create(['building_id' => $this->building->getKey(), 'name' => $name]);
    }

    protected function runImport(bool $dryRun = false): void
    {
        $this->artisan('work-blocks:import', array_filter([
            'path' => 'tests/Fixtures/googlecalendar/ambulance.json',
            '--dry-run' => $dryRun,
        ]))->assertSuccessful();
    }

    protected function profileFor(string $email): StaffProfile
    {
        return User::query()->where('email', $email)->firstOrFail()->staffProfile;
    }

    public function test_imports_therapist_shifts_into_the_right_room(): void
    {
        $this->runImport();

        // 9 blocks across 7 therapists; rentals, device time and the past are excluded.
        $this->assertSame(9, TherapistWorkBlock::query()->count());

        $ema = TherapistWorkBlock::query()->where('therapist_id', $this->profileFor('ema.murcova@friendlyfyzio.cz')->getKey())->firstOrFail();
        $this->assertSame('2026-07-23', $ema->work_date->toDateString());
        $this->assertSame('09:00:00', $ema->start_time);
        $this->assertSame('12:00:00', $ema->end_time);
        $this->assertSame('Ambulance velká', $ema->room->name);

        // "VA" is Ambulance velká (AV reversed).
        $renca = TherapistWorkBlock::query()->where('therapist_id', $this->profileFor('renata.prnka@friendlyfyzio.cz')->getKey())->firstOrFail();
        $this->assertSame('Ambulance velká', $renca->room->name);

        // "AM" is Ambulance malá.
        $denisa = TherapistWorkBlock::query()->where('therapist_id', $this->profileFor('denisa.novakova@friendlyfyzio.cz')->getKey())->firstOrFail();
        $this->assertSame('Ambulance malá', $denisa->room->name);
    }

    public function test_parses_messy_title_formats(): void
    {
        $this->runImport();

        // Dash-less "Lada AV" → Lada in Ambulance velká.
        $lada = TherapistWorkBlock::query()->where('therapist_id', $this->profileFor('lada.cincilova@friendlyfyzio.cz')->getKey())->firstOrFail();
        $this->assertSame('Ambulance velká', $lada->room->name);

        // "Daniela - AM" → Daniela in Ambulance malá.
        $daniela = TherapistWorkBlock::query()->where('therapist_id', $this->profileFor('daniela.steblova@friendlyfyzio.cz')->getKey())->firstOrFail();
        $this->assertSame('Ambulance malá', $daniela->room->name);

        // Bare "Lucka - AV" is Fickerová (not Amani), plus her "Lucka F." block → two blocks.
        $lucie = $this->profileFor('lucie.fickerova@friendlyfyzio.cz');
        $this->assertSame(2, TherapistWorkBlock::query()->where('therapist_id', $lucie->getKey())->count());

        // Room-first "AV - Šárka" is parsed the same as "Šárka - AM": Šárka has two blocks.
        $sarka = $this->profileFor('sarka.antosikova@friendlyfyzio.cz');
        $this->assertSame(2, TherapistWorkBlock::query()->where('therapist_id', $sarka->getKey())->count());
    }

    public function test_skips_rentals_device_time_and_past_and_reports_unknowns(): void
    {
        $this->artisan('work-blocks:import', ['path' => 'tests/Fixtures/googlecalendar/ambulance.json'])
            ->expectsOutputToContain('neznamy x')
            ->assertSuccessful();

        // Kuba/Lucka A. rentals, the "pronájem" and laser/kryo entries, and the
        // past instance never become availability.
        $this->assertSame(0, TherapistWorkBlock::query()->whereDate('work_date', '2026-07-20')->count());
        $this->assertSame(2, TherapistWorkBlock::query()->whereDate('work_date', '2026-07-24')->count()); // Renča + Šárka only
    }

    public function test_is_idempotent(): void
    {
        $this->runImport();
        $this->runImport();

        $this->assertSame(9, TherapistWorkBlock::query()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->runImport(dryRun: true);

        $this->assertSame(0, TherapistWorkBlock::query()->count());
    }
}
