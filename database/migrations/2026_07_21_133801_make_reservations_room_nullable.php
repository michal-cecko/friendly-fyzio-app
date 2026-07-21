<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visits reconstructed from a historical export have no known room, and
     * assigning them a real one would falsely claim it was occupied. Booking
     * still requires a room — ReservationForm keeps the field required.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservations ALTER COLUMN room_id DROP NOT NULL');
        } else {
            Schema::table('reservations', function (Blueprint $table) {
                $table->uuid('room_id')->nullable()->change();
            });
        }

        $this->restorePartialDoubleBookingIndex();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservations ALTER COLUMN room_id SET NOT NULL');
        } else {
            Schema::table('reservations', function (Blueprint $table) {
                $table->uuid('room_id')->nullable(false)->change();
            });
        }

        $this->restorePartialDoubleBookingIndex();
    }

    /**
     * SQLite recreates the whole table for a column change and re-adds indexes
     * without their WHERE clause, which would turn the no-double-booking index
     * into a full unique one and stop a cancelled and an active reservation
     * from sharing a slot. See 2026_07_16_170625_restore_partial_no_double_booking_index.
     */
    protected function restorePartialDoubleBookingIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS reservations_no_double_booking');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX reservations_no_double_booking
            ON reservations (therapist_id, reservation_date, start_time)
            WHERE status <> 'cancelled' AND deleted_at IS NULL
        SQL);
    }
};
