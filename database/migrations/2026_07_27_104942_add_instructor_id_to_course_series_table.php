<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A série can be taught by someone other than the person who owns the course —
 * a second run of the same kurz often has its own lecturer. The column is an
 * override: left empty, the série is led by the course's instructor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            $table->foreignUuid('instructor_id')->nullable()->after('course_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_id');
        });
    }
};
