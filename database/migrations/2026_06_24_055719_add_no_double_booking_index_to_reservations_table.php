<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A partial unique index makes a therapist double-booking impossible at the
     * database level: only one active (non-cancelled, non-deleted) reservation may
     * exist per therapist + date + start time. The slot algorithm already
     * guarantees offered slots never overlap, so constraining the start is enough.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX reservations_no_double_booking
            ON reservations (therapist_id, reservation_date, start_time)
            WHERE status <> 'cancelled' AND deleted_at IS NULL
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reservations_no_double_booking');
    }
};
