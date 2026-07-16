<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            // Who confirmed the reservation (App\Enums\ConfirmationSource: automatic /
            // customer / therapist) and, when a person did, which user.
            $table->string('confirmed_by')->nullable()->after('confirmed_at');
            $table->foreignUuid('confirmed_by_id')->nullable()->after('confirmed_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_by_id');
            $table->dropColumn('confirmed_by');
        });
    }
};
