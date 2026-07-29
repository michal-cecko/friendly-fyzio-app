<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything a live installation needs, and nothing it doesn't: the shared
 * foundation (roles, settings, invoice series, e-mail templates, CMS pages,
 * navigation, categories), then the real team, building, rooms and the Ergobody
 * client history. No demo services, courses, events, reservations or test
 * logins — those belong to {@see DatabaseSeeder}.
 *
 * Fresh install:      artisan migrate:fresh --seed --seeder=ProductionSeeder
 * Existing database:  artisan db:seed --class=ProductionSeeder
 *
 * Idempotent throughout, so re-running after a deploy safely picks up new
 * settings, e-mail templates and CMS content. Expect it to take a few minutes:
 * RealDataSeeder chains the Ergobody import when its exports are present.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        // Staff (incl. the owner super-admin), building and rooms, then the
        // client history and course archive.
        $this->call(RealDataSeeder::class);

        // The team's published qualifications, onto the staff profiles above.
        // Fill-when-empty, so it never fights an edit made in the panel.
        $this->call(StaffQualificationsSeeder::class);

        // Services need the therapists and rooms above: an unstaffed service
        // cannot be booked at all.
        $this->call(ServiceSeeder::class);

        // Marketing pages attach to the categories and to the services, so this
        // runs last.
        $this->call(ServicePagesSeeder::class);

        $this->importLaserReservations();
    }

    /**
     * Imports the Laser+Kryo calendar as laser reservations. Runs here rather
     * than in RealDataSeeder because it needs the "Laserová terapie" service
     * that ServiceSeeder creates above. Gitignored snapshot, so a checkout
     * without it simply skips; tests exercise the command against a fixture.
     */
    protected function importLaserReservations(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $snapshot = base_path('export/googlecalendar/laser-kryo.json');

        if (! is_file($snapshot)) {
            $this->command?->warn("Laser+Kryo snapshot not found at {$snapshot} — skipping laser reservations.");

            return;
        }

        $this->command?->info('Importing laser reservations from the Laser+Kryo calendar…');
        $this->command?->call('laser:import', ['path' => 'export/googlecalendar/laser-kryo.json']);
    }
}
