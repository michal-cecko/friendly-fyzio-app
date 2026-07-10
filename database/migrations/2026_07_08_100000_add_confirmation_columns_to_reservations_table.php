<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            // When the customer confirmation-request e-mail was sent (send-once guard).
            $table->timestamp('confirmation_sent_at')->nullable()->after('payment_status');
            // When the customer confirmed their attendance via the magic link.
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn(['confirmation_sent_at', 'confirmed_at']);
        });
    }
};
