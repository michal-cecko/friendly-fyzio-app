<?php

use App\Support\Lessons\ScheduleFromLessons;
use App\Support\Lessons\ScheduleSlot;
use App\Support\Lessons\SeriesSchedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The room moves from the série onto its rozvrh slots — each row of the rozvrh
 * now names the room its lessons are planned into.
 *
 * One room per série could not express a course meeting v pondělí in one room and
 * ve středu in another, which meant staff generated the lessons and then moved
 * half of them by hand.
 *
 * Nothing is dropped before it is carried over: every slot gets a room first,
 * from the série's own column where one is filled in, otherwise from the lessons
 * the série already has ({@see ScheduleFromLessons}) — that is what gives the
 * imported historical catalogue its rooms, since the import writes them onto the
 * lessons but never onto the série.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfill();

        Schema::table('course_series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });
    }

    public function down(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            $table->foreignUuid('room_id')->nullable()->after('schedule')->constrained()->nullOnDelete();
        });

        foreach ($this->series() as $series) {
            $schedule = SeriesSchedule::fromArray(json_decode((string) $series->schedule, true));

            if ($schedule->isEmpty()) {
                continue;
            }

            // The old shape holds one room for the whole série, so the first slot's
            // room wins — reversing this is inherently lossy.
            DB::table('course_series')->where('id', $series->id)->update([
                'room_id' => $schedule->roomIds()[0] ?? null,
                'schedule' => json_encode(array_map(
                    fn (array $slot): array => array_diff_key($slot, ['room_id' => null]),
                    $schedule->toArray(),
                )),
            ]);
        }
    }

    protected function backfill(): void
    {
        $derived = $this->roomsFromLessons();

        foreach ($this->series() as $series) {
            $schedule = SeriesSchedule::fromArray(json_decode((string) $series->schedule, true));

            if ($schedule->isEmpty()) {
                continue;
            }

            $fallback = $derived->get($series->id);

            $slots = array_map(fn (ScheduleSlot $slot): ScheduleSlot => new ScheduleSlot(
                $slot->day,
                $slot->startTime,
                $slot->endTime,
                // What staff picked on the série wins; the lessons only fill in what
                // it never had. A slot the lessons say nothing about stays roomless —
                // the rozvrh then still states its day and time, it just cannot
                // generate until someone picks a room.
                $series->room_id ?? $fallback?->get($slot->key())?->roomId,
            ), $schedule->slots());

            DB::table('course_series')
                ->where('id', $series->id)
                ->update(['schedule' => json_encode(SeriesSchedule::fromSlots($slots)->toArray())]);
        }
    }

    /**
     * The rooms each série's own lessons meet in, keyed by série and then by slot.
     *
     * @return Collection<string, Collection<string, ScheduleSlot>>
     */
    protected function roomsFromLessons(): Collection
    {
        $derive = new ScheduleFromLessons;

        // Cancelled lessons say nothing about where the série usually meets.
        return DB::table('lessons')
            ->whereNotNull('series_id')
            ->whereNull('deleted_at')
            ->get(['series_id', 'lesson_date', 'start_time', 'end_time', 'room_id'])
            ->groupBy('series_id')
            ->map(fn (Collection $lessons): Collection => collect($derive->fromRows($lessons)->slots())
                ->keyBy(fn (ScheduleSlot $slot): string => $slot->key()));
    }

    /**
     * @return Collection<int, object>
     */
    protected function series(): Collection
    {
        return DB::table('course_series')->whereNotNull('schedule')->get(['id', 'schedule', 'room_id']);
    }
};
