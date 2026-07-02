<?php

namespace Tests\Feature;

use App\Models\TherapistProfile;
use App\Models\TherapistSpecialization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $profile
     */
    private function makeTherapist(array $profile = []): TherapistProfile
    {
        $user = User::factory()->therapist()->create(['name' => 'Mgr. Lucie Fičkerová']);

        return TherapistProfile::factory()->for($user)->create(array_merge([
            'slug' => 'lucie-fickerova',
            'title' => 'Fyzioterapeutka, zakladatelka',
            'bio' => '<p>Medailonek terapeutky.</p>',
            'education' => [['degree' => 'Mgr. – Fyzioterapie', 'institution' => 'Ostravská univerzita', 'period' => '2010 – 2012']],
            'certifications' => [['name' => 'DNS kurz', 'institution' => 'FTVS', 'year' => '2021']],
            'published_at' => now(),
        ], $profile));
    }

    public function test_published_profile_renders_all_sections(): void
    {
        $therapist = $this->makeTherapist();
        TherapistSpecialization::factory()->create([
            'therapist_id' => $therapist->getKey(),
            'name' => 'Pánevní dno',
            'icon' => 'heart',
            'description' => 'Terapie dysfunkcí pánevního dna.',
            'display_order' => 0,
        ]);

        $this->get($therapist->permalink)
            ->assertOk()
            ->assertSee('Mgr. Lucie Fičkerová')
            ->assertSee('Fyzioterapeutka, zakladatelka')
            ->assertSee('Medailonek terapeutky', false)   // bio
            ->assertSee('V čem vám mohu pomoci')          // specializations section
            ->assertSee('Pánevní dno')                     // specialization name
            ->assertSee('Terapie dysfunkcí pánevního dna.') // specialization description
            ->assertSee('Mgr. – Fyzioterapie')             // education
            ->assertSee('DNS kurz')                        // certification
            ->assertSee('Objednat se');
    }

    public function test_draft_profile_returns_404_for_guests(): void
    {
        $therapist = $this->makeTherapist(['published_at' => null]);

        $this->get($therapist->permalink)->assertNotFound();
    }

    public function test_draft_profile_is_previewable_by_staff(): void
    {
        $therapist = $this->makeTherapist(['published_at' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get($therapist->permalink)
            ->assertOk()
            ->assertSee('Mgr. Lucie Fičkerová')
            ->assertSee('Náhled', false);
    }

    public function test_slug_is_generated_from_name_when_blank(): void
    {
        $user = User::factory()->therapist()->create(['name' => 'Bc. Petra Nováková']);
        $therapist = TherapistProfile::factory()->for($user)->create(['slug' => null]);

        $this->assertNotNull($therapist->slug);
        $this->assertStringContainsString('petra-novakova', $therapist->slug);
    }
}
