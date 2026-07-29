<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\StaffQualificationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffQualificationsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The Therapist capability auto-creates an empty staff profile, which is
     * exactly the production starting point: a profile with no qualifications.
     */
    private function makeTherapist(string $name, string $email): StaffProfile
    {
        $user = User::factory()->therapist()->create(['name' => $name, 'email' => $email]);

        return $user->staffProfile->refresh();
    }

    private function makeDaniela(): StaffProfile
    {
        return $this->makeTherapist('Daniela Steblová', 'daniela.steblova@friendlyfyzio.cz');
    }

    public function test_fills_education_and_certifications_from_the_seeded_data(): void
    {
        $profile = $this->makeDaniela();

        $this->seed(StaffQualificationsSeeder::class);

        $profile->refresh();

        $this->assertSame([
            'degree' => 'Mgr., Fyzioterapie',
            'institution' => 'Ostravská univerzita v Ostravě, Lékařská fakulta',
            'period' => '2020 – 2023',
        ], $profile->education[0]);

        $this->assertSame([
            'name' => 'Metoda V. Vojty – kurz A',
            'institution' => 'RL-Corpus',
            'year' => '2023',
        ], $profile->certifications[0]);

        // "Stáže" fold into education, "Konference" into certifications.
        $this->assertContains('Stáž ve fyzioterapii', array_column($profile->education, 'degree'));
        $this->assertContains('Konference POROD', array_column($profile->certifications, 'name'));
    }

    public function test_is_idempotent(): void
    {
        $profile = $this->makeDaniela();

        $this->seed(StaffQualificationsSeeder::class);
        $first = $profile->refresh()->only(['education', 'certifications']);

        $stats = app(StaffQualificationsSeeder::class)->sync();

        $this->assertSame(0, $stats['updated'], 'A second run must change nothing.');
        $this->assertSame($first, $profile->refresh()->only(['education', 'certifications']));
    }

    public function test_leaves_hand_edited_qualifications_alone(): void
    {
        $profile = $this->makeDaniela();
        $profile->update([
            'certifications' => [['name' => 'Ručně přidaný kurz', 'institution' => null, 'year' => '2026']],
        ]);

        $stats = app(StaffQualificationsSeeder::class)->sync();

        $profile->refresh();

        $this->assertSame('Ručně přidaný kurz', $profile->certifications[0]['name']);
        $this->assertSame(1, $stats['skipped']);
        // The empty education column is still filled — only the touched field is kept.
        $this->assertNotEmpty($profile->education);
        $this->assertSame(1, $stats['updated']);
    }

    public function test_overwrite_replaces_hand_edited_qualifications(): void
    {
        $profile = $this->makeDaniela();
        $profile->update([
            'certifications' => [['name' => 'Ručně přidaný kurz', 'institution' => null, 'year' => '2026']],
        ]);

        $this->artisan('staff:sync-qualifications', ['--overwrite' => true])->assertSuccessful();

        $this->assertSame(
            'Metoda V. Vojty – kurz A',
            $profile->refresh()->certifications[0]['name'],
        );
    }

    public function test_dry_run_writes_nothing(): void
    {
        $profile = $this->makeDaniela();

        $this->artisan('staff:sync-qualifications', ['--dry-run' => true])->assertSuccessful();

        $this->assertEmpty($profile->refresh()->education);
        $this->assertEmpty($profile->refresh()->certifications);
    }

    public function test_matches_on_name_when_the_email_was_changed_and_reports_the_rest(): void
    {
        $renamed = $this->makeTherapist('Daniela Steblová', 'daniela@jina-adresa.cz');

        $stats = app(StaffQualificationsSeeder::class)->sync();

        $this->assertNotEmpty($renamed->refresh()->education);

        // The other five are simply absent — reported, never created.
        $this->assertCount(5, $stats['missing']);
        $this->assertSame(1, User::query()->therapists()->count());
    }

    public function test_qualifications_render_on_the_public_detail_page(): void
    {
        $profile = $this->makeDaniela();
        $profile->update(['published_at' => now()]);

        $this->seed(StaffQualificationsSeeder::class);

        $this->get($profile->refresh()->permalink)
            ->assertOk()
            ->assertSee('Vzdělání')
            ->assertSee('Vybrané certifikace a kurzy')
            ->assertSee('Mgr., Fyzioterapie')
            ->assertSee('Konference POROD');
    }
}
