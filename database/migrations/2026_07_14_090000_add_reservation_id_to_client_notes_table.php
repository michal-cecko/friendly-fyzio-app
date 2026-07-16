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
        Schema::table('client_notes', function (Blueprint $table) {
            $table->foreignUuid('reservation_id')
                ->nullable()
                ->after('author_id')
                ->constrained('reservations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
