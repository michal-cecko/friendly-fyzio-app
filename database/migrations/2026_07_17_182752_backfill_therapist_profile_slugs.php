<?php

use App\Models\StaffProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill slugs for any staff profiles missing one, so every bookable
     * therapist can be deep-linked into the reservation wizard by slug. The
     * model's saving hook generates the slug from the user's name.
     *
     * The model is StaffProfile because a migration must compile against the
     * code as it stands today, but at this point in the timeline the table is
     * still `therapist_profiles` — it is renamed a few migrations later — so
     * the table is resolved from whichever name currently exists. On a fresh
     * database there is nothing to backfill; profiles created afterwards get
     * their slug from the saving hook.
     */
    public function up(): void
    {
        $table = Schema::hasTable('staff_profiles') ? 'staff_profiles' : 'therapist_profiles';

        if (! Schema::hasTable($table)) {
            return;
        }

        $profile = (new StaffProfile)->setTable($table);

        $profile->newQuery()
            ->whereNull('slug')
            ->with('user')
            ->each(fn (StaffProfile $profile) => $profile->save());
    }

    public function down(): void
    {
        //
    }
};
