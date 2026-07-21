<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // "Vybaveno" — the obligation is fully closed (attended & paid, storno
            // fee paid, or doctor-note waived). Orthogonal to status/payment_status,
            // which keep recording HOW it ended.
            $table->timestamp('settled_at')->nullable()->after('doctor_note_requested_at');
            // Set once staff resolve the promised doctor's note (either outcome).
            $table->timestamp('doctor_note_resolved_at')->nullable()->after('settled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['settled_at', 'doctor_note_resolved_at']);
        });
    }
};
