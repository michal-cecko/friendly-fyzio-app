<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Final step of the workshop + one-time-lesson unification: the data was
 * copied into one_off_events / one_off_event_bookings (UUIDs preserved) by
 * 2026_07_18_200005; the empty legacy tables can go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('workshop_registrations');
        Schema::dropIfExists('one_time_lesson_bookings');
        Schema::dropIfExists('workshops');
        Schema::dropIfExists('one_time_lessons');
    }

    public function down(): void
    {
        // Irreversible — the tables' data lives on in one_off_events /
        // one_off_event_bookings. Restore from backup if truly needed.
    }
};
