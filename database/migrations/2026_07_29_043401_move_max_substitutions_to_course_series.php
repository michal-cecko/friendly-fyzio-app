<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The make-up allowance becomes a property of the série, not of the course.
 * Séries of one course run for different numbers of lessons, so a single
 * course-wide number cannot express "two make-ups over ten lessons, one over
 * six". Substitute *targets* already moved to séries in
 * `2026_07_18_121815_move_substitute_rules_to_series`; this is the last rule
 * that was still set a level up.
 *
 * Existing séries inherit their course's number, so nothing changes for runs
 * already underway. The early-cancel deadline stays on the course — it is a
 * notice period, unaffected by how long a série is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            $table->integer('max_substitutions')->default(0)->after('capacity');
        });

        DB::table('course_series')->update([
            'max_substitutions' => DB::raw(
                '(select coalesce(courses.max_substitutions, 0) from courses where courses.id = course_series.course_id)',
            ),
        ]);

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('max_substitutions');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->integer('max_substitutions')->default(2)->after('description');
        });

        DB::table('courses')->update([
            'max_substitutions' => DB::raw(
                '(select coalesce(max(course_series.max_substitutions), 2) from course_series where course_series.course_id = courses.id)',
            ),
        ]);

        Schema::table('course_series', function (Blueprint $table) {
            $table->dropColumn('max_substitutions');
        });
    }
};
