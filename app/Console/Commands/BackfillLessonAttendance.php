<?php

namespace App\Console\Commands;

use App\Models\CourseSeries;
use App\Support\Enrollments\LessonRoster;
use Illuminate\Console\Command;

/**
 * Fills in the lesson presence lists for séries that predate the roster
 * ({@see LessonRoster}), where attendance rows only existed for excuses and
 * substitute moves. Idempotent — existing rows are left untouched, so it is safe
 * to re-run after an import.
 */
class BackfillLessonAttendance extends Command
{
    protected $signature = 'lessons:backfill-attendance {--series= : Limit to a single course series UUID}';

    protected $description = 'Create the missing lesson attendance rows for every active enrollment';

    public function handle(): int
    {
        $query = CourseSeries::query()->with('lessons');

        if ($seriesId = $this->option('series')) {
            $query->whereKey($seriesId);
        }

        $created = 0;
        $touched = 0;

        $query->each(function (CourseSeries $series) use (&$created, &$touched): void {
            $rows = LessonRoster::forSeries($series);

            if ($rows > 0) {
                $this->line(sprintf('  %s — %d řádků docházky', $series->name, $rows));
                $touched++;
            }

            $created += $rows;
        });

        $this->info(sprintf('Doplněno %d řádků docházky v %d sériích.', $created, $touched));

        return self::SUCCESS;
    }
}
