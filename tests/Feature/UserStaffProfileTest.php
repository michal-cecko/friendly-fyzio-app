<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\ViewUser;
use App\Models\Specialization;
use App\Models\StaffProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public team profile is edited inline on the User form (no standalone
 * resource, no relation-manager tab) and saved when the User is submitted.
 */
class UserStaffProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_editing_a_therapist_saves_the_embedded_profile_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create();
        $profile = $therapist->staffProfile; // auto-created with the capability

        Livewire::test(EditUser::class, ['record' => $therapist->getKey()])
            ->fillForm([
                'staffProfile.title' => 'Fyzioterapeutka',
                'staffProfile.published_at' => now(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $profile->refresh();
        $this->assertSame('Fyzioterapeutka', $profile->title);
        $this->assertTrue($profile->isPublished());
        // Slug stays auto-derived from the name, never editable in the form.
        $this->assertNotEmpty($profile->slug);
    }

    public function test_creating_a_therapist_with_profile_data_persists_exactly_one_profile(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Jana Nováková',
                'email' => 'jana@example.com',
                'capabilities' => [Capability::Therapist->value],
                'staffProfile.title' => 'Fyzioterapeutka',
                'staffProfile.published_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'jana@example.com')->firstOrFail();

        // ensureStaffProfile() must not create a second profile on top of the
        // one saved through the form's relationship group.
        $this->assertSame(1, StaffProfile::where('user_id', $user->getKey())->count());
        $this->assertSame('Fyzioterapeutka', $user->staffProfile->title);
        $this->assertStringContainsString('jana-novakova', $user->staffProfile->slug);
    }

    public function test_full_profile_round_trips_a_specialization_row(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create();
        $specialization = Specialization::factory()->create(['name' => 'Dětská fyzioterapie']);

        Livewire::test(EditUser::class, ['record' => $therapist->getKey()])
            ->fillForm([
                'staffProfile.specializations' => [
                    ['specialization_id' => $specialization->getKey()],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('therapist_specializations', [
            'therapist_id' => $therapist->staffProfile->getKey(),
            'specialization_id' => $specialization->getKey(),
        ]);
    }

    public function test_a_therapist_gets_an_auto_created_profile_with_a_slug(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'Jana Terapeutka']);

        $this->assertNotNull($therapist->staffProfile);
        $this->assertStringContainsString('jana-terapeutka', $therapist->staffProfile->slug);
    }

    public function test_public_profile_tab_visible_for_staff_and_hidden_for_plain_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create();
        $plainAdmin = User::factory()->admin()->create();

        Livewire::test(EditUser::class, ['record' => $therapist->getKey()])
            ->assertSee('Veřejný profil');

        Livewire::test(EditUser::class, ['record' => $plainAdmin->getKey()])
            ->assertDontSee('Veřejný profil');
    }

    public function test_view_page_shows_capabilities_and_the_full_public_profile(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create([
            'title_before' => 'Bc.',
            'title_after' => 'DiS.',
        ]);

        $therapist->staffProfile->update([
            'title' => 'Fyzioterapeutka',
            'education' => [['degree' => 'Bc. Fyzioterapie', 'institution' => 'UK', 'period' => '2010 – 2013']],
            'published_at' => now(),
        ]);
        $therapist->staffProfile->specializations()->create([
            'specialization_id' => Specialization::factory()->create(['name' => 'Dětská fyzioterapie'])->getKey(),
        ]);

        Livewire::test(ViewUser::class, ['record' => $therapist->getKey()])
            ->assertOk()
            // Capabilities replace the stale legacy `role` badge.
            ->assertSeeText('Schopnosti')
            ->assertSeeText('Terapeut')
            ->assertDontSeeText('Typ účtu')
            // Academic titles are surfaced individually.
            ->assertSeeText('Titul před jménem')
            ->assertSeeText('Bc.')
            ->assertSeeText('DiS.')
            // The full public profile card renders.
            ->assertSeeText('Veřejný profil')
            ->assertSeeText('Fyzioterapeutka')
            ->assertSeeText('Bc. Fyzioterapie')
            ->assertSeeText('Dětská fyzioterapie');
    }

    public function test_view_page_hides_the_public_profile_for_a_plain_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $plainAdmin = User::factory()->admin()->create();

        Livewire::test(ViewUser::class, ['record' => $plainAdmin->getKey()])
            ->assertOk()
            ->assertDontSeeText('Veřejný profil');
    }
}
