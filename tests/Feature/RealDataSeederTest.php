<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Models\Building;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RealDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRealData(): void
    {
        $this->seed(RealDataSeeder::class);
    }

    public function test_prunes_external_room_renters_seeded_by_earlier_installs(): void
    {
        // An earlier install seeded Lucie Amani as a lecturer with a staff
        // profile (and thus panel access). She only rents rooms, so a re-seed
        // must remove her account and her cascade-linked profile.
        $amani = User::factory()->create(['email' => 'lucie.amani@friendlyfyzio.cz']);
        $amani->syncCapabilities([Capability::Lecturer]);
        $this->assertNotNull($amani->staffProfile);

        $this->seedRealData();

        $this->assertSame(0, User::query()->where('email', 'lucie.amani@friendlyfyzio.cz')->count());
        $this->assertSame(0, StaffProfile::query()->where('user_id', $amani->getKey())->count());
    }

    public function test_seeds_the_team_with_roles_profiles_and_specializations(): void
    {
        $this->seedRealData();

        $lucie = User::query()->where('email', 'lucie.fickerova@friendlyfyzio.cz')->firstOrFail();
        $this->assertTrue($lucie->isSuperAdmin());
        $this->assertTrue($lucie->isAdmin(), 'A super-admin is also an admin.');
        $this->assertTrue($lucie->isTherapist());
        $this->assertSame('Mgr.', $lucie->title_before);
        $this->assertSame('Lucie Fickerová', $lucie->name);
        $this->assertTrue($lucie->isTherapist());
        $this->assertNotNull($lucie->staffProfile?->published_at);
        $this->assertSame(
            ['Urogynekologická fyzioterapie', 'Těhotenská fyzioterapie', 'Terapie jizev', 'Onkologická fyzioterapie – rakovina prsu'],
            $lucie->staffProfile->specializations()->orderBy('display_order')->with('specialization')->get()->pluck('specialization.name')->all(),
        );

        // Adéla is admin-only but still presented on the team page.
        $adela = User::query()->where('email', 'adela.macurova@friendlyfyzio.cz')->firstOrFail();
        $this->assertTrue($adela->isAdmin());
        $this->assertFalse($adela->isTherapist());
        $this->assertFalse($adela->isTherapist());
        $this->assertNotNull($adela->staffProfile?->published_at);
        $this->assertSame('Asistentka', $adela->staffProfile->title);

        // Lucie Amani and Jakub Trepáč only rent rooms — neither is seeded as
        // staff, so no account, no profile, and no place on the team grid.
        $this->assertSame(0, StaffProfile::query()->where('is_collaborator', true)->count());
        $this->assertSame(0, User::query()->where('email', 'lucie.amani@friendlyfyzio.cz')->count());

        // The owner super-admin: seeded here but off the public team (no profile).
        $owner = User::query()->where('email', 'ceckomichal@gmail.com')->firstOrFail();
        $this->assertSame('Michal Čečko', $owner->name);
        $this->assertTrue($owner->isSuperAdmin());
        $this->assertNull($owner->staffProfile);

        // The revenue overview is granted explicitly, to the owner and Lucie only.
        $this->assertTrue($owner->canViewRevenue());
        $this->assertTrue($lucie->canViewRevenue());
        $this->assertFalse($adela->canViewRevenue(), 'Being an admin does not reveal the takings.');

        $this->assertSame(10, StaffProfile::query()->count());
        // 8 pure therapists plus Lucie Fickerová, who is a super-admin and a therapist.
        $this->assertSame(9, User::query()->therapists()->count());
    }

    public function test_seeds_building_and_rooms_with_shortcuts(): void
    {
        $this->seedRealData();

        $building = Building::query()->where('name', 'Hlavní budova')->firstOrFail();
        $this->assertSame('Zednická 1109/2, Ostrava-Poruba', $building->address);

        $rooms = Room::query()->where('building_id', $building->getKey())->pluck('short_name', 'name');

        $this->assertSame([
            'Ambulance velká' => 'AV',
            'Ambulance malá' => 'AM',
            'Tělocvična velká' => 'TV',
            'Tělocvična malá' => 'TM',
        ], $rooms->all());
    }

    public function test_is_idempotent(): void
    {
        $this->seedRealData();
        $this->seed(RealDataSeeder::class);

        // 10 team members + the owner super-admin (who has no team profile).
        $this->assertSame(11, User::query()->count());
        $this->assertSame(10, StaffProfile::query()->count());
        $this->assertSame(1, Building::query()->count());
        $this->assertSame(4, Room::query()->count());
    }
}
