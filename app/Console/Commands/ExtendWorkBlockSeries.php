<?php

namespace App\Console\Commands;

use App\Models\TherapistWorkBlockSeries;
use App\Support\WorkBlocks\WorkBlockGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps open-ended work-block series materialized up to the rolling horizon
 * (WorkBlockGenerator::HORIZON_WEEKS ahead). Runs daily; only appends dates
 * beyond each series' generated_until cursor, so deleted or edited
 * occurrences are never recreated.
 */
class ExtendWorkBlockSeries extends Command
{
    protected $signature = 'work-blocks:extend';

    protected $description = 'Materialize open-ended therapist work-block series up to the rolling horizon.';

    public function handle(WorkBlockGenerator $generator): int
    {
        $horizon = WorkBlockGenerator::horizon();

        $created = 0;
        $skipped = 0;

        TherapistWorkBlockSeries::query()
            ->whereDate('generated_until', '<', $horizon)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('ends_on')
                ->orWhereColumn('ends_on', '>', 'generated_until'))
            ->each(function (TherapistWorkBlockSeries $series) use ($generator, $horizon, &$created, &$skipped): void {
                $result = $generator->materialize($series, $horizon);

                $created += $result['created'];
                $skipped += $result['skipped'];
            });

        $this->info("Vytvořeno {$created} bloků pracovní doby, {$skipped} přeskočeno kvůli překryvu.");

        return self::SUCCESS;
    }
}
