<?php

namespace Database\Seeders;

use App\Console\Commands\CoursesImport;
use App\Enums\Capability;
use App\Models\Building;
use App\Models\Room;
use App\Models\Specialization;
use App\Models\StaffProfile;
use App\Models\TherapistSpecialization;
use App\Models\User;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the real Friendly Fyzio production data: the staff team from
 * friendlyfyzio.cz/nas-tym (users, therapist profiles, specializations,
 * photos), the real building with its rooms, and then the client history from
 * the Ergobody exports.
 *
 * The staff must exist before the import runs: it attaches the seven staff
 * members who are treated here to their own accounts and resolves note
 * signatures against the team, so the order in run() is load-bearing.
 *
 * Idempotent — safe to re-run; NOT part of DatabaseSeeder (dev keeps demo
 * data), run explicitly via `artisan db:seed --class=RealDataSeeder`.
 */
class RealDataSeeder extends Seeder
{
    use ImportsMedia;

    /** Where the gitignored Ergobody CSV exports live, relative to the project. */
    protected const string ERGOBODY_PATH = 'export/ergobody';

    /** Where the gitignored Google Sheets exports (courses, vouchers) live. */
    protected const string GOOGLESHEETS_PATH = 'export/googlesheets';

    /** Where the gitignored Google Calendar snapshots (work blocks) live. */
    protected const string GOOGLECALENDAR_PATH = 'export/googlecalendar';

    /** The site owner / developer account (kept off the public team page). */
    protected const string OWNER_EMAIL = 'ceckomichal@gmail.com';

    /**
     * External room-renters who were seeded as staff in earlier installs: they
     * only rent rooms, so they must have no account and no panel access. Pruned
     * on every run so the seeder converges. See {@see pruneExternalRentalStaff}.
     *
     * @var list<string>
     */
    protected const array EXTERNAL_RENTAL_EMAILS = [
        'lucie.amani@friendlyfyzio.cz',
    ];

    public function run(): void
    {
        $this->seedStaff();
        $this->pruneExternalRentalStaff();
        $this->seedOwner();
        $this->seedBuildingAndRooms();
        $this->importErgobodyExport();
        $this->importCoursesExport();
        $this->importWorkBlocksExport();
    }

    /**
     * Remove any external room-renter previously seeded as staff (e.g. Lucie
     * Amani). A hard delete is required — {@see User} soft-deletes, and only a
     * real DELETE cascades their staff profile and their own rental
     * courses/events (all unpublished and enrollment-free). {@see CoursesImport}
     * skips their catalogue rows, so nothing is recreated. Idempotent: a no-op
     * once the account is gone.
     */
    protected function pruneExternalRentalStaff(): void
    {
        User::query()
            ->withTrashed()
            ->whereIn('email', self::EXTERNAL_RENTAL_EMAILS)
            ->get()
            ->each(function (User $user): void {
                $this->command?->warn("Removing external room-renter {$user->email} — not a team member.");
                $user->forceDelete();
            });
    }

    /**
     * Imports therapist work blocks from the ambulance Google Calendar snapshot
     * (needs the staff profiles and ambulance rooms above). Gitignored, so a
     * checkout without the snapshot simply skips it; tests exercise the command
     * against a fixture.
     */
    protected function importWorkBlocksExport(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $snapshot = base_path(self::GOOGLECALENDAR_PATH.'/ambulance.json');

        if (! is_file($snapshot)) {
            $this->command?->warn("Ambulance calendar snapshot not found at {$snapshot} — skipping work blocks.");

            return;
        }

        $this->command?->info('Importing therapist work blocks from the ambulance calendar…');
        $this->command?->call('work-blocks:import', ['path' => self::GOOGLECALENDAR_PATH.'/ambulance.json']);
    }

    /**
     * The owner/developer's login — a super-administrator who may grant
     * admin/super-admin capabilities and delete other admins. Deliberately NOT
     * part of the public {@see staff()} team array: this account belongs to
     * nobody on /o-nas, holds only the SuperAdmin capability, gets no therapist
     * profile and stays out of the booking flow.
     */
    protected function seedOwner(): void
    {
        $user = User::query()->firstOrNew(['email' => self::OWNER_EMAIL]);

        $user->fill(['name' => 'Michal Čečko']);

        if (! $user->exists) {
            // Never hardcode a credential. The initial password comes from the
            // OWNER_INITIAL_PASSWORD env var; without it, a random one is set and
            // the owner uses "zapomenuté heslo". The value is never printed.
            $initial = (string) env('OWNER_INITIAL_PASSWORD', '');

            // The 'hashed' cast hashes this on assignment.
            $user->forceFill(['password' => $initial !== '' ? $initial : Str::password(24)]);

            $this->command?->warn($initial !== ''
                ? sprintf('Created super-administrator %s from OWNER_INITIAL_PASSWORD — change it after signing in.', self::OWNER_EMAIL)
                : sprintf('Created super-administrator %s with a random password — use "zapomenuté heslo" to set one.', self::OWNER_EMAIL));
        }

        $user->email_verified_at ??= now();
        $user->save();

        $user->syncCapabilities([Capability::SuperAdmin, Capability::Revenue]);
    }

    /**
     * Imports the client history. The exports are gitignored, so a checkout
     * without them still gets a usable team and building instead of failing —
     * and tests skip it, since they exercise the import against fixtures.
     */
    protected function importErgobodyExport(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $directory = base_path(self::ERGOBODY_PATH);

        if (! glob($directory.'/*Kartot*.csv')) {
            $this->command?->warn("Ergobody export not found in {$directory} — skipping client history.");

            return;
        }

        $this->command?->info('Importing Ergobody client history (this takes a few minutes)…');
        $this->command?->call('ergobody:import', ['path' => self::ERGOBODY_PATH]);
    }

    /**
     * Imports the historical autumn-2025 course catalogue and rosters. Runs
     * after the client history so roster names resolve against imported
     * clients; gitignored, so a checkout without the sheets simply skips it.
     */
    protected function importCoursesExport(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $directory = base_path(self::GOOGLESHEETS_PATH);

        if (! glob($directory.'/*Seznam*.csv')) {
            $this->command?->warn("Course catalogue not found in {$directory} — skipping course history.");

            return;
        }

        $this->command?->info('Importing historical courses, workshops and enrollments…');
        $this->command?->call('courses:import', ['path' => self::GOOGLESHEETS_PATH]);
    }

    protected function seedStaff(): void
    {
        foreach ($this->staff() as $order => $member) {
            $user = User::query()->firstOrNew(['email' => $member['email']]);

            $user->fill([
                'name' => $member['name'],
                'title_before' => $member['title_before'] ?? null,
            ]);

            // Staff set their own password via the "zapomenuté heslo" flow.
            if (! $user->exists) {
                $user->forceFill(['password' => Str::password(40)]);
            }

            $user->email_verified_at ??= now();
            $user->save();

            $user->syncCapabilities($member['capabilities']);

            $profile = StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'title' => $member['title'],
                    'photo' => $this->teamPhoto($member['photo'], $member['name']),
                    'is_collaborator' => $member['is_collaborator'] ?? false,
                    'display_order' => $order,
                    'published_at' => now(),
                ],
            );

            foreach ($member['specializations'] ?? [] as $specOrder => $specialization) {
                // Resolve (or create) the shared catalog entry, then link the therapist to it.
                $catalogEntry = Specialization::query()->firstOrCreate(
                    ['name' => $specialization['name']],
                    ['icon' => $specialization['icon']],
                );

                TherapistSpecialization::query()->updateOrCreate(
                    [
                        'therapist_id' => $profile->getKey(),
                        'specialization_id' => $catalogEntry->getKey(),
                    ],
                    [
                        'display_order' => $specOrder,
                    ],
                );
            }
        }
    }

    protected function seedBuildingAndRooms(): void
    {
        $building = Building::query()->updateOrCreate(
            ['name' => 'Hlavní budova'],
            ['address' => 'Zednická 1109/2, Ostrava-Poruba'],
        );

        $rooms = [
            'Ambulance velká' => 'AV',
            'Ambulance malá' => 'AM',
            'Tělocvična velká' => 'TV',
            'Tělocvična malá' => 'TM',
        ];

        foreach ($rooms as $name => $shortName) {
            Room::query()->updateOrCreate(
                ['building_id' => $building->getKey(), 'name' => $name],
                ['short_name' => $shortName],
            );
        }
    }

    protected function teamPhoto(string $file, string $name): ?int
    {
        $path = database_path("seeders/data/team/{$file}");

        // Skipped in tests: importing 12 real photos through the media library
        // (file copies + conversions) is slow and irrelevant to what's asserted.
        if (app()->runningUnitTests() || ! is_file($path)) {
            return null;
        }

        return $this->mediaFromPath($path, "Tým – {$name}");
    }

    /**
     * The team as presented on friendlyfyzio.cz/nas-tym, in page order.
     *
     * @return list<array{
     *     name: string,
     *     title_before?: string,
     *     email: string,
     *     capabilities: list<Capability>,
     *     title: string,
     *     photo: string,
     *     is_collaborator?: bool,
     *     specializations?: list<array{name: string, icon: string}>,
     * }>
     */
    protected function staff(): array
    {
        return [
            [
                'name' => 'Lucie Fickerová',
                'title_before' => 'Mgr.',
                'email' => 'lucie.fickerova@friendlyfyzio.cz',
                // The clinic's owner: a super-administrator (may manage admins),
                // who also practises as a physiotherapist and sees the takings.
                'capabilities' => [Capability::SuperAdmin, Capability::Therapist, Capability::Revenue],
                'title' => 'Fyzioterapeut',
                'photo' => 'lucie-fickerova.jpg',
                'specializations' => [
                    ['name' => 'Urogynekologická fyzioterapie', 'icon' => 'heart'],
                    ['name' => 'Těhotenská fyzioterapie', 'icon' => 'baby'],
                    ['name' => 'Terapie jizev', 'icon' => 'activity'],
                    ['name' => 'Onkologická fyzioterapie – rakovina prsu', 'icon' => 'ribbon'],
                ],
            ],
            [
                'name' => 'Renáta Prnka',
                'title_before' => 'Mgr.',
                'email' => 'renata.prnka@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Fyzioterapeut',
                'photo' => 'renata-prnka.jpg',
                'specializations' => [
                    ['name' => 'Urogynekologická fyzioterapie', 'icon' => 'heart'],
                    ['name' => 'Těhotenská fyzioterapie', 'icon' => 'baby'],
                    ['name' => 'Terapie jizev', 'icon' => 'activity'],
                    ['name' => 'Terapie čelistního kloubu', 'icon' => 'smile'],
                    ['name' => 'Onkologická fyzioterapie', 'icon' => 'ribbon'],
                ],
            ],
            [
                'name' => 'Šárka Antošíková',
                'email' => 'sarka.antosikova@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Fyzioterapeut',
                'photo' => 'sarka-antosikova.jpg',
                'specializations' => [
                    ['name' => 'Urogynekologická fyzioterapie', 'icon' => 'heart'],
                    ['name' => 'Terapie čelistního kloubu', 'icon' => 'smile'],
                    ['name' => 'Fyzioterapie nohy', 'icon' => 'footprints'],
                    ['name' => 'Terapie jizev', 'icon' => 'activity'],
                ],
            ],
            [
                'name' => 'Lada Činčilová',
                'title_before' => 'Mgr.',
                'email' => 'lada.cincilova@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Fyzioterapeut',
                'photo' => 'lada-cincilova.jpg',
                'specializations' => [
                    ['name' => 'Urogynekologická fyzioterapie', 'icon' => 'heart'],
                    ['name' => 'Terapie čelistního kloubu', 'icon' => 'smile'],
                    ['name' => 'Těhotenská fyzioterapie', 'icon' => 'baby'],
                    ['name' => 'Terapie jizev', 'icon' => 'activity'],
                    ['name' => 'Access Bars', 'icon' => 'sparkles'],
                ],
            ],
            [
                'name' => 'Ema Murčová',
                'title_before' => 'Bc.',
                'email' => 'ema.murcova@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Fyzioterapeut',
                'photo' => 'ema-murcova.jpg',
                'specializations' => [
                    ['name' => 'Těhotenská fyzioterapie', 'icon' => 'baby'],
                    ['name' => 'Urogynekologická fyzioterapie', 'icon' => 'heart'],
                    ['name' => 'Terapie jizev', 'icon' => 'activity'],
                    ['name' => 'SM systém', 'icon' => 'move'],
                ],
            ],
            [
                'name' => 'Daniela Steblová',
                'title_before' => 'Mgr.',
                'email' => 'daniela.steblova@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Fyzioterapeut',
                'photo' => 'daniela-steblova.jpg',
                'specializations' => [
                    ['name' => 'Těhotenská fyzioterapie', 'icon' => 'baby'],
                    ['name' => 'Urogynekologická fyzioterapie', 'icon' => 'heart'],
                ],
            ],
            [
                'name' => 'Michaela Hrubá',
                'email' => 'michaela.hruba@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Kondiční trenér',
                'photo' => 'michaela-hruba.jpg',
            ],
            [
                'name' => 'Denisa Nováková',
                'email' => 'denisa.novakova@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Masér, lektor, bylinná napářka',
                'photo' => 'denisa-novakova.jpg',
                'specializations' => [
                    ['name' => 'Masáže žen a dětí', 'icon' => 'hand'],
                    ['name' => 'Bylinná napářka', 'icon' => 'leaf'],
                ],
            ],
            [
                'name' => 'Kristýna Černá',
                'email' => 'kristyna.cerna@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Lektorka jógy',
                'photo' => 'kristyna-cerna.jpg',
            ],
            [
                'name' => 'Adéla Macurová',
                'email' => 'adela.macurova@friendlyfyzio.cz',
                'capabilities' => [Capability::Admin],
                'title' => 'Asistentka',
                'photo' => 'adela-macurova.jpg',
            ],
            // Lucie Amani and Jakub Trepáč are external third parties who only
            // rent rooms — not team members, with no account and no panel access.
            // Both are kept off the seeded staff and off /o-nas' data-driven team
            // grid; a static "Externí spolupráce" brick presents them below the
            // team (see PageSeeder). Amani's own courses are her rentals, so
            // CoursesImport skips them rather than importing/attributing her.
        ];
    }
}
