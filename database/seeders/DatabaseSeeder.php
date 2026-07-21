<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The development database: the shared foundation plus demo data and test
 * logins. For a live installation use {@see ProductionSeeder}, which seeds the
 * same foundation with the real team and client history instead.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        // The User "saved" event syncs the matching Shield role from the account type
        // (Administrátor -> super_admin, Terapeut -> therapist), so no manual assignRole needed.
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@friendly-fyzio.test',
        ]);

        User::factory()->therapist()->create([
            'name' => 'Terapeut',
            'email' => 'therapist@friendly-fyzio.test',
        ]);

        // DemoSeeder creates the services; ServicePagesSeeder attaches custom pages to them.
        $this->call(DemoSeeder::class);
        $this->call(ServicePagesSeeder::class);

        // A demo customer carrying every client-zone state (needs DemoSeeder's
        // services, courses and workshops to exist first).
        $this->call(ClientZoneDemoSeeder::class);
    }
}
