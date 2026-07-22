<?php

namespace Database\Seeders;

use App\Enums\Capability;
use App\Models\Building;
use App\Models\Room;
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

    public function run(): void
    {
        $this->seedStaff();
        $this->seedBuildingAndRooms();
        $this->importErgobodyExport();
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
                TherapistSpecialization::query()->updateOrCreate(
                    [
                        'therapist_id' => $profile->getKey(),
                        'name' => $specialization['name'],
                    ],
                    [
                        'icon' => $specialization['icon'],
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
                'capabilities' => [Capability::Admin, Capability::Therapist],
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
            [
                'name' => 'Lucie Amani',
                'email' => 'lucie.amani@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Pohybový specialista',
                'photo' => 'lucie-amani.jpg',
                'is_collaborator' => true,
                'specializations' => [
                    ['name' => 'SM systém', 'icon' => 'move'],
                    ['name' => 'Terapie pánevního dna', 'icon' => 'heart'],
                    ['name' => 'Bylinná napářka', 'icon' => 'leaf'],
                ],
            ],
            [
                'name' => 'Jakub Trepáč',
                'title_before' => 'Mgr.',
                'email' => 'jakub.trepac@friendlyfyzio.cz',
                'capabilities' => [Capability::Therapist],
                'title' => 'Fyzioterapeut',
                'photo' => 'jakub-trepac.jpg',
                'is_collaborator' => true,
                'specializations' => [
                    ['name' => 'Dětská fyzioterapie', 'icon' => 'baby'],
                ],
            ],
        ];
    }
}
