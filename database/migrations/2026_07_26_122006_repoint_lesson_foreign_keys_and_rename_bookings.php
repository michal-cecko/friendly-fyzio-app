<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points everything that referenced `course_lessons` or `one_off_events` at the
 * merged `lessons` table. The UUIDs were copied verbatim, so no stored value
 * changes — only the constraint target.
 *
 * `one_off_event_bookings` becomes `lesson_bookings`: a booking is a booking
 * whether the lesson stands alone or belongs to a série, and calling a drop-in
 * on a course lesson a "one-off event booking" would be actively misleading.
 *
 * Foreign keys are dropped by COLUMN, never by name — SQLite refuses the latter
 * and rebuilds the table for the former, which is what the test suite runs on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->dropForeign(['lesson_id']);
            });

            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->foreign('lesson_id')->references('id')->on('lessons')->cascadeOnDelete();
            });

            Schema::table('substitute_tokens', function (Blueprint $table) {
                $table->dropForeign(['source_lesson_id']);
                $table->dropForeign(['used_for_lesson_id']);
            });

            Schema::table('substitute_tokens', function (Blueprint $table) {
                $table->foreign('source_lesson_id')->references('id')->on('lessons')->cascadeOnDelete();
                $table->foreign('used_for_lesson_id')->references('id')->on('lessons')->nullOnDelete();
            });

            // Drop the constraint while the table still carries its old name:
            // PostgreSQL does not rename constraints along with their table, but
            // dropForeign() derives the name from the table's current one.
            Schema::table('one_off_event_bookings', function (Blueprint $table) {
                $table->dropForeign(['one_off_event_id']);
            });

            Schema::rename('one_off_event_bookings', 'lesson_bookings');

            Schema::table('lesson_bookings', function (Blueprint $table) {
                $table->renameColumn('one_off_event_id', 'lesson_id');
            });

            Schema::table('lesson_bookings', function (Blueprint $table) {
                $table->foreign('lesson_id')->references('id')->on('lessons')->cascadeOnDelete();
            });
        });

        $this->restoreAttendanceUniqueIndex();
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('lesson_bookings', function (Blueprint $table) {
                $table->dropForeign(['lesson_id']);
                $table->renameColumn('lesson_id', 'one_off_event_id');
            });

            Schema::rename('lesson_bookings', 'one_off_event_bookings');

            Schema::table('one_off_event_bookings', function (Blueprint $table) {
                $table->foreign('one_off_event_id')->references('id')->on('one_off_events')->cascadeOnDelete();
            });

            Schema::table('substitute_tokens', function (Blueprint $table) {
                $table->dropForeign(['source_lesson_id']);
                $table->dropForeign(['used_for_lesson_id']);
            });

            Schema::table('substitute_tokens', function (Blueprint $table) {
                $table->foreign('source_lesson_id')->references('id')->on('course_lessons')->cascadeOnDelete();
                $table->foreign('used_for_lesson_id')->references('id')->on('course_lessons')->nullOnDelete();
            });

            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->dropForeign(['lesson_id']);
            });

            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->foreign('lesson_id')->references('id')->on('course_lessons')->cascadeOnDelete();
            });
        });
    }

    /**
     * SQLite rebuilds the whole table to change a foreign key, and a rebuild is
     * exactly where indexes go missing. "A client can only be on a lesson's
     * presence list once" is a rule the substitute engine relies on, so put it
     * back if the rebuild dropped it.
     */
    private function restoreAttendanceUniqueIndex(): void
    {
        $exists = collect(Schema::getIndexes('lesson_attendances'))
            ->contains(fn (array $index): bool => $index['unique']
                && count($index['columns']) === 2
                && in_array('enrollment_id', $index['columns'], true)
                && in_array('lesson_id', $index['columns'], true));

        if ($exists) {
            return;
        }

        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->unique(['enrollment_id', 'lesson_id']);
        });
    }
};
