<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Specialization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupSpecializationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_groups_specializations_by_override_and_exact_name_match(): void
    {
        $pelvic = Service::factory()->create(['name' => 'Terapie pánevního dna']);
        $steam = Service::factory()->create(['name' => 'Bylinná napářka']);

        // Differs from any service name → resolved via the override map.
        $viaOverride = Specialization::factory()->create(['name' => 'Pánevní dno', 'service_id' => null]);
        // Matches a service name exactly → resolved automatically.
        $viaExactMatch = Specialization::factory()->create(['name' => 'Bylinná napářka', 'service_id' => null]);

        $this->artisan('specializations:group')->assertSuccessful();

        $this->assertSame($pelvic->getKey(), $viaOverride->refresh()->service_id);
        $this->assertSame($steam->getKey(), $viaExactMatch->refresh()->service_id);
    }

    public function test_dry_run_reports_but_does_not_persist(): void
    {
        Service::factory()->create(['name' => 'Terapie pánevního dna']);
        $specialization = Specialization::factory()->create(['name' => 'Pánevní dno', 'service_id' => null]);

        $this->artisan('specializations:group --dry-run')->assertSuccessful();

        $this->assertNull($specialization->refresh()->service_id);
    }

    public function test_unmatched_specialization_is_left_ungrouped(): void
    {
        // No service and no override entry for this name.
        $specialization = Specialization::factory()->create(['name' => 'Access Bars', 'service_id' => null]);

        $this->artisan('specializations:group')
            ->expectsOutputToContain('Access Bars')
            ->assertSuccessful();

        $this->assertNull($specialization->refresh()->service_id);
    }
}
