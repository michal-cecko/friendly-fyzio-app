<?php

use App\Support\Lessons\LessonScheduleGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A course série meets on a repeating schedule — one or more weekdays, at the
 * same time, in the same room — but until now that pattern lived nowhere: only
 * the individual lessons carried a date, and each one had to be added by hand.
 *
 * Recording the recurrence on the série is what lets
 * {@see LessonScheduleGenerator} materialize the lessons
 * between start_date and end_date, and re-run later to fill in only what is
 * missing.
 *
 * Every column is nullable: séries created before this migration have no
 * schedule, and a série may deliberately keep an irregular, hand-built one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            // Several weekdays per série (e.g. Po + St), sharing one time and room.
            $table->json('days_of_week')->nullable()->after('end_date');
            $table->time('start_time')->nullable()->after('days_of_week');
            $table->time('end_time')->nullable()->after('start_time');
            // lessons.room_id is NOT NULL, so a generated lesson needs one here.
            $table->foreignUuid('room_id')->nullable()->after('end_time')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn(['days_of_week', 'start_time', 'end_time']);
        });
    }
};
