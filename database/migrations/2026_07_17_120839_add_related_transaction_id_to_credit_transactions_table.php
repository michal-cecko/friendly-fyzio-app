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
            // Expiration rows point at the top-up they expire — the marker that
            // makes the credits:expire command idempotent.
            $table->foreignUuid('related_transaction_id')
                ->nullable()
                ->after('expires_at')
                ->constrained('credit_transactions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_transaction_id');
        });
    }
};
