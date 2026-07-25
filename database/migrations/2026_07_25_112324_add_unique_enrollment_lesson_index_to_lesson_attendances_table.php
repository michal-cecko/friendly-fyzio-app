<?php

use App\Models\LessonAttendance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A client can only be on a lesson's presence list once. Every writer already
 * keys on the pair (`updateOrCreate`), and now that the roster is generated in
 * bulk the constraint has to be real.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->removeDuplicates();

        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->unique(['enrollment_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->dropUnique(['enrollment_id', 'lesson_id']);
        });
    }

    /**
     * Keeps the most telling row of each duplicated pair: a cancellation or a
     * recorded presence outranks an untouched one.
     */
    private function removeDuplicates(): void
    {
        $duplicated = DB::table('lesson_attendances')
            ->select('enrollment_id', 'lesson_id')
            ->groupBy('enrollment_id', 'lesson_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicated as $pair) {
            $keep = LessonAttendance::query()
                ->where('enrollment_id', $pair->enrollment_id)
                ->where('lesson_id', $pair->lesson_id)
                ->orderByDesc('cancelled_at')
                ->orderByDesc('attended')
                ->orderBy('created_at')
                ->first();

            LessonAttendance::query()
                ->where('enrollment_id', $pair->enrollment_id)
                ->where('lesson_id', $pair->lesson_id)
                ->whereKeyNot($keep->getKey())
                ->delete();
        }
    }
};
