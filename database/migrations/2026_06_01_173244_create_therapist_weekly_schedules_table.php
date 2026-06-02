<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_weekly_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('therapist_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->string('day_of_week');
            $table->string('week_type')->default('all');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_weekly_schedules');
    }
};
