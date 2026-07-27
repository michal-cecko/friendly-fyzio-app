<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Presence became a decision rather than a tick: everybody on a lesson's list is
 * present unless they were excused. `attended` used to mean "somebody ticked
 * this off", so every untouched row is sitting at false — which now reads as an
 * absence nobody entered. Bring the column in line with what the list says.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('lesson_attendances')
            ->whereNull('cancelled_at')
            ->update(['attended' => true]);
    }

    /**
     * The old meaning cannot be recovered — nothing recorded which rows had been
     * ticked — so rolling back leaves the data alone rather than inventing
     * absences.
     */
    public function down(): void {}
};
