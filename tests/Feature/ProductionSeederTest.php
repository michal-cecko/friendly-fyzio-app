<?php

namespace Tests\Feature;

use App\Enums\ServiceVisibility;
use App\Enums\UserRole;
use App\Models\Building;
use App\Models\Course;
use App\Models\EmailTemplate;
use App\Models\Navigation;
use App\Models\OneOffEvent;
use App\Models\Page;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function seedProduction(): void
    {
        // Run through Artisan rather than $this->seed(): RolePermissionSeeder
        // shells out to shield:generate, which needs a real console output.
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class])->assertSuccessful();
    }

    public function test_seeds_the_configuration_the_app_cannot_run_without(): void
    {
        $this->seedProduction();

        $this->assertGreaterThan(0, Setting::query()->count());
        $this->assertGreaterThan(0, EmailTemplate::query()->count());
        $this->assertGreaterThan(0, Page::query()->count());
        $this->assertGreaterThan(0, Navigation::query()->count());
        $this->assertGreaterThan(0, ServiceCategory::query()->count());
    }

    public function test_seeds_the_real_team_building_and_rooms(): void
    {
        $this->seedProduction();

        $this->assertSame(12, StaffProfile::query()->count());
        $this->assertNotNull(User::query()->where('email', 'lucie.fickerova@friendlyfyzio.cz')->first());

        $building = Building::query()->where('name', 'Hlavní budova')->firstOrFail();
        $this->assertSame(
            ['AV', 'AM', 'TV', 'TM'],
            Room::query()->where('building_id', $building->getKey())->pluck('short_name')->all(),
        );
    }

    public function test_seeds_the_owner_administrator_account(): void
    {
        $this->seedProduction();

        $owner = User::query()->where('email', 'ceckomichal@gmail.com')->firstOrFail();

        $this->assertSame(UserRole::Admin, $owner->role);
        $this->assertTrue($owner->isStaff());
        $this->assertTrue(Hash::check('FriendlyFyzio2026!', $owner->password));
        $this->assertNotNull($owner->email_verified_at);

        // He is not on the team page and must not be bookable.
        $this->assertFalse($owner->acts_as_therapist);
        $this->assertNull($owner->staffProfile);
    }

    public function test_re_running_does_not_reset_a_changed_owner_password(): void
    {
        $this->seedProduction();

        $owner = User::query()->where('email', 'ceckomichal@gmail.com')->firstOrFail();
        $owner->forceFill(['password' => 'a-password-the-owner-chose'])->save();

        $this->seedProduction();

        $this->assertTrue(
            Hash::check('a-password-the-owner-chose', $owner->fresh()->password),
            'Seeding must not clobber a password changed in the app.',
        );
    }

    public function test_contains_no_demo_data_or_test_logins(): void
    {
        $this->seedProduction();

        $this->assertSame(0, Course::query()->count(), 'Demo courses must not reach a live install.');
        $this->assertSame(0, OneOffEvent::query()->count());
        $this->assertSame(0, Reservation::query()->count());
        $this->assertSame(0, User::query()->where('role', UserRole::Customer)->count());

        foreach (['admin@friendly-fyzio.test', 'therapist@friendly-fyzio.test'] as $testLogin) {
            $this->assertNull(
                User::query()->where('email', $testLogin)->first(),
                "The {$testLogin} development account must not exist in production.",
            );
        }

    }

    public function test_seeds_a_bookable_service_catalogue(): void
    {
        $this->seedProduction();

        // Six physiotherapy services plus four massages; the herbal steam,
        // laser and cryotherapy are hidden from the public wizard.
        $this->assertSame(10, Service::query()->bookable()->count());

        $entry = Service::query()->where('slug', 'vstupni-vysetreni-pohymoveho-aparatu')->first()
            ?? Service::query()->where('name', 'Vstupní vyšetření pohybového aparátu')->firstOrFail();

        $this->assertSame(1200, $entry->price);
        $this->assertSame(90, $entry->duration_minutes);
        $this->assertSame(6, $entry->therapists()->count());
        $this->assertNotNull($entry->cancellationRule);

        // Šárka does not offer pregnancy care, so that pair is one smaller.
        $pregnancy = Service::query()->where('name', 'Těhotenská fyzioterapie')->firstOrFail();
        $this->assertSame(5, $pregnancy->therapists()->count());

        // Hidden still means staff-bookable, so it needs its therapists.
        $laser = Service::query()->where('name', 'Laserová terapie')->firstOrFail();
        $this->assertSame(ServiceVisibility::Hidden, $laser->visibility);
        $this->assertGreaterThan(0, $laser->therapists()->count());

        // Adéla holds a profile for the team page but does not treat clients.
        $adela = User::query()->where('email', 'adela.macurova@friendlyfyzio.cz')->firstOrFail();
        $this->assertFalse(
            $laser->therapists()->whereKey($adela->staffProfile?->getKey())->exists(),
            'An administrator who does not treat clients must not be offered as a therapist.',
        );
    }

    public function test_is_idempotent(): void
    {
        $this->seedProduction();

        $users = User::query()->count();
        $pages = Page::query()->count();
        $settings = Setting::query()->count();

        $this->seedProduction();

        $this->assertSame($users, User::query()->count());
        $this->assertSame($pages, Page::query()->count());
        $this->assertSame($settings, Setting::query()->count());
    }
}
