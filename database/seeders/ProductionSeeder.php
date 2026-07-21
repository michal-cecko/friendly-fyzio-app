<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
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
    /** The owner's account, kept separate from the team seeded by RealDataSeeder. */
    protected const string OWNER_EMAIL = 'ceckomichal@gmail.com';

    /**
     * Only ever applied when the account is first created, so a password
     * changed in the app survives re-running the seeder. Change it after the
     * first sign-in.
     */
    protected const string OWNER_INITIAL_PASSWORD = 'FriendlyFyzio2026!';

    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        // Staff, building and rooms, then the client history.
        $this->call(RealDataSeeder::class);

        // Services need the therapists and rooms above: an unstaffed service
        // cannot be booked at all.
        $this->call(ServiceSeeder::class);

        // Marketing pages attach to the categories and to the services, so this
        // runs last.
        $this->call(ServicePagesSeeder::class);

        $this->seedOwnerAccount();
    }

    /**
     * The owner's administrator login. Deliberately not part of RealDataSeeder,
     * which mirrors the public team page: this account belongs to nobody on
     * /o-nas and must never show up there. As a plain administrator without
     * `acts_as_therapist` it also gets no therapist profile, so it stays out of
     * the booking flow.
     */
    protected function seedOwnerAccount(): void
    {
        $user = User::query()->firstOrNew(['email' => self::OWNER_EMAIL]);

        $user->fill([
            'name' => 'Michal Cecko',
            'role' => UserRole::Admin,
        ]);

        if (! $user->exists) {
            // The 'hashed' cast on the model hashes this on assignment.
            $user->forceFill(['password' => self::OWNER_INITIAL_PASSWORD]);

            $this->command?->warn(sprintf(
                'Created administrator %s with the initial password "%s" — change it after signing in.',
                self::OWNER_EMAIL,
                self::OWNER_INITIAL_PASSWORD,
            ));
        }

        $user->email_verified_at ??= now();
        $user->save();
    }
}
