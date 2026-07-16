<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_work_block_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('therapist_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week');
            $table->string('week_type')->default('all');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('generated_until');
            $table->timestamps();
        });

        Schema::create('therapist_work_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('therapist_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('series_id')->nullable()->constrained('therapist_work_block_series')->nullOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['work_date', 'therapist_id']);
            $table->index(['therapist_id', 'work_date']);
            $table->index('series_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_work_blocks');
        Schema::dropIfExists('therapist_work_block_series');
    }
};
