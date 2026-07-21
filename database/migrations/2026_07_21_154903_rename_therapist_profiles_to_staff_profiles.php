<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The profile carries a person's public presentation — position, bio, photo,
 * education — for everyone shown on /o-nas, including staff who never treat
 * clients (the assistant, the yoga instructor). Calling it a "therapist"
 * profile was wrong: who may be booked is decided by User::isTherapist() plus
 * having a bookable service, never by owning a profile.
 *
 * The `therapist_id` foreign keys pointing here are deliberately left alone —
 * whoever sits on a reservation, work block or service link genuinely acts as
 * its therapist, and renaming them would rewrite the reservations table and
 * its partial no-double-booking index for no semantic gain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('therapist_profiles', 'staff_profiles');
    }

    public function down(): void
    {
        Schema::rename('staff_profiles', 'therapist_profiles');
    }
};
