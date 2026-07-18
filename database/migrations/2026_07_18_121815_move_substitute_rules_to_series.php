<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Substitute rules become série-specific (source série → target série) instead of
 * course → course. A course → course rule cannot be mapped losslessly onto the
 * finer série granularity, so existing rows are dropped; staff re-create them per
 * série. The table is recreated rather than altered so the change is identical on
 * MySQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('substitute_rules');

        Schema::create('substitute_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_series_id')->constrained('course_series')->cascadeOnDelete();
            $table->foreignUuid('target_series_id')->constrained('course_series')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitute_rules');

        Schema::create('substitute_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('target_course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
