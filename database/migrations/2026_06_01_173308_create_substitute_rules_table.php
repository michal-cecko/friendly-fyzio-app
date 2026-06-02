<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitute_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('target_course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitute_rules');
    }
};
