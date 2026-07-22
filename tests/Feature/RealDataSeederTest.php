<?php

namespace Tests\Feature;

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

    public function test_seeds_the_team_with_roles_profiles_and_specializations(): void
    {
        $this->seedRealData();

        $lucie = User::query()->where('email', 'lucie.fickerova@friendlyfyzio.cz')->firstOrFail();
        $this->assertTrue($lucie->isAdmin());
        $this->assertTrue($lucie->isTherapist());
        $this->assertSame('Mgr.', $lucie->title_before);
        $this->assertSame('Lucie Fickerová', $lucie->name);
        $this->assertTrue($lucie->isTherapist());
        $this->assertNotNull($lucie->staffProfile?->published_at);
        $this->assertSame(
            ['Urogynekologická fyzioterapie', 'Těhotenská fyzioterapie', 'Terapie jizev', 'Onkologická fyzioterapie – rakovina prsu'],
            $lucie->staffProfile->specializations()->orderBy('display_order')->pluck('name')->all(),
        );

        // Adéla is admin-only but still presented on the team page.
        $adela = User::query()->where('email', 'adela.macurova@friendlyfyzio.cz')->firstOrFail();
        $this->assertTrue($adela->isAdmin());
        $this->assertFalse($adela->isTherapist());
        $this->assertFalse($adela->isTherapist());
        $this->assertNotNull($adela->staffProfile?->published_at);
        $this->assertSame('Asistentka', $adela->staffProfile->title);

        $this->assertSame(
            2,
            StaffProfile::query()->where('is_collaborator', true)->count(),
        );

        $this->assertSame(12, StaffProfile::query()->count());
        // 10 pure therapists plus Lucie, who is an admin and a therapist.
        $this->assertSame(11, User::query()->therapists()->count());
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

        $this->assertSame(12, User::query()->count());
        $this->assertSame(12, StaffProfile::query()->count());
        $this->assertSame(1, Building::query()->count());
        $this->assertSame(4, Room::query()->count());
    }
}
