<?php

use App\Models\TherapistProfile;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill slugs for any therapist profiles missing one, so every bookable
     * therapist can be deep-linked into the reservation wizard by slug. The
     * model's saving hook generates the slug from the user's name.
     */
    public function up(): void
    {
        TherapistProfile::query()
            ->whereNull('slug')
            ->with('user')
            ->each(fn (TherapistProfile $therapist) => $therapist->save());
    }

    public function down(): void
    {
        //
    }
};
