<?php

namespace Tests\Feature;

use App\Console\Commands\ErgobodyImport;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneUnverifiedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_old_unverified_customer_with_no_activity(): void
    {
        $abandoned = User::factory()->customer()->unverified()->create([
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('users:prune-unverified')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $abandoned->getKey()]);
    }

    public function test_keeps_unverified_customer_that_has_a_reservation(): void
    {
        $customer = User::factory()->customer()->unverified()->create([
            'created_at' => now()->subDays(5),
        ]);

        Reservation::factory()->create(['client_id' => $customer->getKey()]);

        $this->artisan('users:prune-unverified')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $customer->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_keeps_recent_verified_and_staff_accounts(): void
    {
        $recent = User::factory()->customer()->unverified()->create(['created_at' => now()->subHour()]);
        $verified = User::factory()->customer()->create(['created_at' => now()->subDays(5)]);
        $therapist = User::factory()->therapist()->unverified()->create(['created_at' => now()->subDays(5)]);

        $this->artisan('users:prune-unverified')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $recent->getKey()]);
        $this->assertDatabaseHas('users', ['id' => $verified->getKey()]);
        $this->assertDatabaseHas('users', ['id' => $therapist->getKey()]);
    }

    public function test_keeps_unverified_customer_that_has_a_tag(): void
    {
        $imported = User::factory()->customer()->unverified()->create([
            'created_at' => now()->subYears(2),
        ]);

        $imported->attachTag(ErgobodyImport::IMPORT_TAG);

        $this->artisan('users:prune-unverified')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $imported->getKey(),
            'deleted_at' => null,
        ]);
    }
}
