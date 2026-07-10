<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            // Numeric per-payment id used as the QR-Platba variable symbol (auto-filled
            // in the Payment model; kept cross-DB, so no Postgres sequence here).
            $table->unsignedBigInteger('number')->nullable()->unique()->after('id');
        });

        // The column shipped with a legacy 'pending' default that is not a PaymentStatus
        // case; normalise it (and any existing rows) so the new enum cast never trips.
        DB::table('payments')->where('status', 'pending')->update(['status' => 'unpaid']);

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('status')->default('unpaid')->change();
        });

        Schema::table('reservations', function (Blueprint $table): void {
            // Set when a late-cancel customer opts to supply a doctor's note (fee waived pending review).
            $table->timestamp('doctor_note_requested_at')->nullable()->after('confirmed_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            // Full deactivation (e.g. late-cancel refusal to pay): blocks login + online booking.
            $table->timestamp('deactivated_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('number');
            $table->string('status')->default('pending')->change();
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn('doctor_note_requested_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('deactivated_at');
        });
    }
};
