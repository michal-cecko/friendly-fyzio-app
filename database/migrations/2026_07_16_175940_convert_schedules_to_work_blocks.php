<?php

use App\Enums\WeekType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Converts the old recurring working-hours data to materialized work blocks:
 * every weekly schedule becomes an open-ended series with 26 weeks of dated
 * rows, non-standard dates copy 1:1, and calendar blocks (vacations) delete
 * the generated rows they covered. The three legacy tables are then dropped.
 *
 * Uses DB::table() + inline date maths only, so the migration stays frozen
 * even as models and WorkBlockGenerator evolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        $backfillFrom = Carbon::today()->startOfWeek();
        $horizon = Carbon::today()->addWeeks(26);
        $now = Carbon::now();

        DB::transaction(function () use ($backfillFrom, $horizon, $now): void {
            $existing = [];

            $insertBlock = function (array $row) use (&$existing, $now): void {
                $key = "{$row['therapist_id']}|{$row['work_date']}|{$row['start_time']}";

                if (isset($existing[$key])) {
                    return;
                }

                $existing[$key] = true;

                DB::table('therapist_work_blocks')->insert([
                    ...$row,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            };

            // 1. Weekly schedules → open-ended series + 26 weeks of dated rows.
            foreach (DB::table('therapist_weekly_schedules')->get() as $schedule) {
                $weekType = WeekType::from($schedule->week_type);

                $firstDate = $backfillFrom->copy();
                while (strtolower($firstDate->englishDayOfWeek) !== $schedule->day_of_week || ! $weekType->matchesDate($firstDate)) {
                    $firstDate->addDay();
                }

                $seriesId = (string) Str::uuid7();

                DB::table('therapist_work_block_series')->insert([
                    'id' => $seriesId,
                    'therapist_id' => $schedule->therapist_id,
                    'room_id' => $schedule->room_id,
                    'day_of_week' => $schedule->day_of_week,
                    'week_type' => $schedule->week_type,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'starts_on' => $firstDate->toDateString(),
                    'ends_on' => null,
                    'generated_until' => $horizon->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                for ($date = $firstDate->copy(); $date->lte($horizon); $date->addWeek()) {
                    if (! $weekType->matchesDate($date)) {
                        continue;
                    }

                    $insertBlock([
                        'id' => (string) Str::uuid7(),
                        'therapist_id' => $schedule->therapist_id,
                        'room_id' => $schedule->room_id,
                        'series_id' => $seriesId,
                        'work_date' => $date->toDateString(),
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'note' => null,
                    ]);
                }
            }

            // 2. Non-standard dates → one-off rows.
            foreach (DB::table('therapist_nonstandard_dates')->get() as $nonstandard) {
                $insertBlock([
                    'id' => (string) Str::uuid7(),
                    'therapist_id' => $nonstandard->therapist_id,
                    'room_id' => $nonstandard->room_id,
                    'series_id' => null,
                    'work_date' => Carbon::parse($nonstandard->work_date)->toDateString(),
                    'start_time' => $nonstandard->start_time,
                    'end_time' => $nonstandard->end_time,
                    'note' => $nonstandard->note,
                ]);
            }

            // 3. Calendar blocks (vacation/sick) → delete the covered rows so
            //    already-entered absences stay honored.
            foreach (DB::table('calendar_blocks')->get() as $block) {
                DB::table('therapist_work_blocks')
                    ->where('therapist_id', $block->therapist_id)
                    ->whereBetween('work_date', [
                        Carbon::parse($block->start_date)->toDateString(),
                        Carbon::parse($block->end_date)->toDateString(),
                    ])
                    ->delete();
            }
        });

        Schema::dropIfExists('therapist_weekly_schedules');
        Schema::dropIfExists('therapist_nonstandard_dates');
        Schema::dropIfExists('calendar_blocks');
    }

    /**
     * Recreates the legacy tables empty; the converted data is not restored.
     */
    public function down(): void
    {
        Schema::create('therapist_weekly_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('therapist_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->string('day_of_week');
            $table->string('week_type')->default('all');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('therapist_nonstandard_dates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('therapist_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('therapist_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }
};
