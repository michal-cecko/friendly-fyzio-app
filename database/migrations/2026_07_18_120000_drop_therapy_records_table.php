<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `therapy_records` was never wired to any UI or write path — the built
 * `client_notes` (surfaced as "Poznámky z terapií") covers therapy notes. Drop
 * the orphaned table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('therapy_records');
    }

    public function down(): void
    {
        Schema::create('therapy_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('therapist_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }
};
