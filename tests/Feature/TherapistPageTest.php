<?php

namespace Tests\Feature;

use App\Enums\SettingValueType;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Specialization;
use App\Models\StaffProfile;
use App\Models\TherapistSpecialization;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TherapistPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $profile
     */
    private function makeTherapist(array $profile = []): StaffProfile
    {
        $user = User::factory()->therapist()->create(['name' => 'Mgr. Lucie Fičkerová']);

        return StaffProfile::factory()->for($user)->create(array_merge([
            'slug' => 'lucie-fickerova',
            'title' => 'Fyzioterapeutka, zakladatelka',
            'bio' => '<p>Medailonek terapeutky.</p>',
            'education' => [['degree' => 'Mgr. – Fyzioterapie', 'institution' => 'Ostravská univerzita', 'period' => '2010 – 2012']],
            'certifications' => [['name' => 'DNS kurz', 'institution' => 'FTVS', 'year' => '2021']],
            'published_at' => now(),
        ], $profile));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function giveSpecialization(StaffProfile $therapist, array $attributes = []): Specialization
    {
        $specialization = Specialization::factory()->create(array_merge([
            'name' => 'Pánevní dno',
            'icon' => 'heart',
            'description' => 'Terapie dysfunkcí pánevního dna.',
        ], $attributes));

        TherapistSpecialization::factory()->create([
            'therapist_id' => $therapist->getKey(),
            'specialization_id' => $specialization->getKey(),
            'display_order' => 0,
        ]);

        return $specialization;
    }

    public function test_published_profile_renders_all_sections(): void
    {
        $therapist = $this->makeTherapist();
        $this->giveSpecialization($therapist, ['service_id' => Service::factory()->create()]);

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

    public function test_long_qualification_lists_are_capped_behind_a_show_more_button(): void
    {
        // Every item is rendered — the cap is a `hidden` class the button lifts,
        // so nothing is withheld from crawlers or from a visitor without JS.
        $therapist = $this->makeTherapist([
            'certifications' => collect(range(1, 9))
                ->map(fn (int $i): array => ['name' => "Kurz {$i}", 'institution' => null, 'year' => (string) (2016 + $i)])
                ->all(),
        ]);

        $page = $this->get($therapist->permalink)->assertOk();

        $page->assertSee('Kurz 1')
            ->assertSee('Kurz 9')
            ->assertSee('data-show-more="certifikace"', false)
            // 9 courses, 3 shown: the remaining 6 take the [5,*] genitive plural.
            ->assertSee('Zobrazit dalších 6 kurzů')
            ->assertSee('Zobrazit méně');

        // The single seeded education row stays under the cap, so no button.
        $this->assertSame(1, substr_count($page->getContent(), 'data-show-more='));
        $this->assertSame(6, substr_count($page->getContent(), 'data-show-more-item'));
    }

    public function test_short_qualification_lists_render_without_a_button(): void
    {
        $therapist = $this->makeTherapist([
            'certifications' => [['name' => 'Jediný kurz', 'institution' => null, 'year' => '2024']],
        ]);

        $this->get($therapist->permalink)
            ->assertOk()
            ->assertSee('Jediný kurz')
            ->assertDontSee('data-show-more=', false)
            ->assertDontSee('data-show-more-item', false);
    }

    public function test_a_mapped_specialization_books_that_service_with_this_therapist(): void
    {
        $therapist = $this->makeTherapist();
        $service = Service::factory()->create(['slug' => 'terapie-panevniho-dna']);
        $this->giveSpecialization($therapist, ['service_id' => $service->getKey()]);

        // Both parameters together land the visitor on the date step: the wizard
        // derives the category (and exam type) from the service on mount.
        $this->get($therapist->permalink)
            ->assertOk()
            // Escaped, because that is how the href is written into the markup.
            ->assertSee(route('reservation.wizard', [
                'terapeut' => $therapist->slug,
                'sluzba' => $service->slug,
            ]));
    }

    public function test_a_specialization_without_a_service_is_not_offered(): void
    {
        $therapist = $this->makeTherapist();
        $this->giveSpecialization($therapist, [
            'name' => 'Access Bars',
            'service_id' => null,
        ]);

        // Nowhere to send anyone, so no card — the section disappears with it.
        $this->get($therapist->permalink)
            ->assertOk()
            ->assertDontSee('V čem vám mohu pomoci');
    }

    public function test_profile_never_exposes_personal_contact_details(): void
    {
        $therapist = $this->makeTherapist();
        $therapist->user->update([
            'phone' => '+420 777 111 222',
            'email' => 'lucie@example.com',
        ]);
        Setting::query()->updateOrCreate(
            ['key' => 'web.address'],
            ['value' => 'Nádražní 100, Ostrava', 'type' => SettingValueType::Text, 'group' => 'web', 'label' => 'Adresa'],
        );
        Cache::forget(Settings::CACHE_KEY);

        $html = $this->get($therapist->permalink)
            ->assertOk()
            ->assertDontSee('+420 777 111 222')
            ->assertDontSee('777111222')
            ->assertDontSee('lucie@example.com')
            // Booking online is the only call to action left on a profile.
            ->assertDontSee('Zavolat')
            ->assertSee('Objednat se')
            ->getContent();

        // The address survives in the shared footer but is off the profile itself.
        $hero = str($html)->between('<h1', '</section>')->toString();
        $this->assertStringContainsString('Objednat se', $hero, 'Hero section was not extracted.');
        $this->assertStringNotContainsString('Nádražní 100, Ostrava', $hero);
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
        $therapist = StaffProfile::factory()->for($user)->create(['slug' => null]);

        $this->assertNotNull($therapist->slug);
        $this->assertStringContainsString('petra-novakova', $therapist->slug);
    }
}
