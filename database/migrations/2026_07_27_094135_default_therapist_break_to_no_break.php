<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nobody takes a break after every visit by default (owner, 2026-07-27).
 *
 * `staff_profiles.break_blocks` arrived defaulting to one block, on the reading
 * that the spec's 15 minutes were a standing turnaround. They are not: a break
 * is something an individual therapist asks for, so the default becomes none and
 * every existing profile is reset to it. A therapist who wants one sets it on
 * their profile or per service assignment — the pivot overrides are untouched
 * here, since a null one means "inherit" and a set one was an explicit choice.
 *
 * Reservations keep the break they were booked with
 * (`reservations.break_minutes`), so no existing booking moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->unsignedInteger('break_blocks')->default(0)->change();
        });

        DB::table('staff_profiles')->update(['break_blocks' => 0]);
    }

    /**
     * Only the default comes back. Restoring a block for everyone would invent a
     * break for therapists who had deliberately set none.
     */
    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->unsignedInteger('break_blocks')->default(1)->change();
        });
    }
};
