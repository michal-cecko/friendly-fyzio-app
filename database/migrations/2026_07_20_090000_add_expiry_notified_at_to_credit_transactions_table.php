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
        Schema::table('credit_transactions', function (Blueprint $table) {
            // Set once the "credit is about to expire" reminder has been sent for a
            // top-up, so the daily credits:notify-expiring command never double-sends.
            $table->timestamp('expiry_notified_at')
                ->nullable()
                ->after('related_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropColumn('expiry_notified_at');
        });
    }
};
