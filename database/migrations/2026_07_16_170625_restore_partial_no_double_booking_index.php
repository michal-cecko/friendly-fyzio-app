<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Re-creates the no-double-booking index WITH its partial predicate. On SQLite
     * (the test database) any later column drop/change on `reservations` recreates
     * the whole table, and Laravel's SQLite grammar re-adds indexes without their
     * WHERE clause — the 2026_07_08 reservation migrations silently turned the
     * partial index into a full unique one, forbidding a cancelled and an active
     * reservation from ever sharing a slot. Postgres was never affected; the
     * drop-and-recreate is idempotent there. If another migration recreates the
     * reservations table on SQLite, this fix must be repeated after it.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS reservations_no_double_booking');

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
        // Intentionally kept: the partial index is the correct shape from the
        // original 2026_06_24 migration; there is nothing older to restore.
    }
};
