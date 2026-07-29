<?php

use App\Support\Lessons\ScheduleFromLessons;
use App\Support\Lessons\ScheduleSlot;
use App\Support\Lessons\SeriesSchedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A série's rozvrh becomes a list of slots — one row per meeting, each with its
 * own weekday and time — instead of a day list sharing a single time.
 *
 * The old shape could not express a série meeting on středa at 9:00 and čtvrtek
 * at 10:30, which the imported autumn-2025 catalogue actually contains, and the
 * rozvrh is now shown to clients on the public course page, so it has to be able
 * to state the truth.
 *
 * Nothing is dropped before it is carried over: every série's days_of_week +
 * start_time + end_time is rewritten as slots first. Séries that never got the
 * old columns filled in — which is all of them in the current data, since the
 * historical import builds lessons directly — have their real recurrence read
 * back out of their lessons instead (see {@see ScheduleFromLessons}), so the
 * rozvrh is populated everywhere rather than only where someone typed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            // [{"day": "tuesday", "start_time": "17:30", "end_time": "18:30"}, …]
            $table->json('schedule')->nullable()->after('end_date');
        });

        $this->backfill();

        Schema::table('course_series', function (Blueprint $table) {
            $table->dropColumn(['days_of_week', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            $table->json('days_of_week')->nullable()->after('end_date');
            $table->time('start_time')->nullable()->after('days_of_week');
            $table->time('end_time')->nullable()->after('start_time');
        });

        foreach (DB::table('course_series')->whereNotNull('schedule')->get(['id', 'schedule']) as $series) {
            $slots = collect(json_decode((string) $series->schedule, true) ?: [])
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): ?ScheduleSlot => ScheduleSlot::fromArray($row))
                ->filter()
                ->values();

            if ($slots->isEmpty()) {
                continue;
            }

            // The old shape holds one time for every day, so the first slot's time
            // wins — reversing this is inherently lossy.
            DB::table('course_series')->where('id', $series->id)->update([
                'days_of_week' => json_encode($slots->map(fn (ScheduleSlot $slot): string => $slot->day->value)->unique()->values()->all()),
                'start_time' => $slots->first()->startTime.':00',
                'end_time' => $slots->first()->endTime.':00',
            ]);
        }

        Schema::table('course_series', function (Blueprint $table) {
            $table->dropColumn('schedule');
        });
    }

    protected function backfill(): void
    {
        $derive = new ScheduleFromLessons;

        // Cancelled lessons are excluded: the rozvrh describes when the série
        // meets, and a scrapped session says nothing about that.
        $lessons = DB::table('lessons')
            ->whereNotNull('series_id')
            ->whereNull('deleted_at')
            ->get(['series_id', 'lesson_date', 'start_time', 'end_time'])
            ->groupBy('series_id');

        foreach (DB::table('course_series')->get(['id', 'days_of_week', 'start_time', 'end_time']) as $series) {
            // What staff typed wins — carrying the old columns over is the whole
            // point. Deriving from lessons only fills séries that have no rozvrh
            // to lose.
            $schedule = $this->fromLegacyColumns($series);

            if ($schedule->isEmpty()) {
                $schedule = $derive->fromRows($lessons->get($series->id) ?? collect());
            }

            if ($schedule->isEmpty()) {
                continue;
            }

            DB::table('course_series')
                ->where('id', $series->id)
                ->update(['schedule' => json_encode($schedule->toArray())]);
        }
    }

    protected function fromLegacyColumns(object $series): SeriesSchedule
    {
        $days = json_decode((string) ($series->days_of_week ?? ''), true);

        return SeriesSchedule::fromArray(
            collect(is_array($days) ? $days : [])
                ->map(fn (mixed $day): array => [
                    'day' => $day,
                    'start_time' => $series->start_time,
                    'end_time' => $series->end_time,
                ])
                ->all()
        );
    }
};
