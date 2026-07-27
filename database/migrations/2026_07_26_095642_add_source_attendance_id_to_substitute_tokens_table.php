<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A token belongs to the excuse that minted it, not merely to its lesson.
 * Without this, `LessonAttendance::substituteToken()` matched on the lesson
 * alone and showed one client's token on every other client's row; the undo path
 * had to guess with a `latest('created_at')` on top.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('substitute_tokens', function (Blueprint $table) {
            $table->foreignUuid('source_attendance_id')->nullable()->after('source_lesson_id')
                ->constrained('lesson_attendances')->cascadeOnDelete();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('substitute_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_attendance_id');
        });
    }

    /**
     * Existing tokens are matched back to their excuse by the pair the old
     * lookup used: the source lesson and the client behind the enrollment.
     */
    private function backfill(): void
    {
        DB::table('substitute_tokens')->orderBy('id')->chunkById(200, function ($tokens): void {
            foreach ($tokens as $token) {
                $attendanceId = DB::table('lesson_attendances')
                    ->join('course_enrollments', 'course_enrollments.id', '=', 'lesson_attendances.enrollment_id')
                    ->where('lesson_attendances.lesson_id', $token->source_lesson_id)
                    ->where('course_enrollments.client_id', $token->client_id)
                    ->orderByDesc('lesson_attendances.cancelled_at')
                    ->value('lesson_attendances.id');

                if ($attendanceId === null) {
                    continue;
                }

                DB::table('substitute_tokens')
                    ->where('id', $token->id)
                    ->update(['source_attendance_id' => $attendanceId]);
            }
        });
    }
};
