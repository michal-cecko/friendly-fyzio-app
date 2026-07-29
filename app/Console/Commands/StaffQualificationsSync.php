<?php

namespace App\Console\Commands;

use Database\Seeders\StaffQualificationsSeeder;
use Illuminate\Console\Command;

/**
 * Flags front-end for {@see StaffQualificationsSeeder}. `db:seed` cannot carry
 * custom options — its own `--force` means "skip the production prompt" — so
 * --overwrite and --dry-run live here. Plain `db:seed --class=…` still works and
 * behaves like this command with no flags.
 */
class StaffQualificationsSync extends Command
{
    protected $signature = 'staff:sync-qualifications
        {--overwrite : Replace qualifications even on profiles that already have some}
        {--dry-run : Report what would change without writing anything}';

    protected $description = 'Doplní vzdělání a certifikace členů týmu z webu friendlyfyzio.cz/nas-tym.';

    public function handle(StaffQualificationsSeeder $seeder): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $stats = $seeder->sync((bool) $this->option('overwrite'), $dryRun);

        $this->newLine();
        $this->info($dryRun ? 'Dry run summary:' : 'Sync summary:');

        $this->table(['Metrika', 'Počet'], [
            ['Doplněné profily', $stats['updated']],
            ['Přeskočená pole (už vyplněná)', $stats['skipped']],
        ]);

        if ($stats['missing'] !== []) {
            $this->warn('Bez profilu v databázi (review these):');

            foreach ($stats['missing'] as $person) {
                $this->line("  {$person}");
            }
        }

        return self::SUCCESS;
    }
}
