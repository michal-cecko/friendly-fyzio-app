<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes background-built invoice ZIPs older than 24 hours from the private
 * disk (local or S3 alike) — the download links promise exactly that lifetime.
 */
class PruneInvoiceExports extends Command
{
    protected $signature = 'invoices:prune-exports';

    protected $description = 'Smaže ZIP exporty faktur starší než 24 hodin.';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDay()->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('invoice-exports') as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("Smazáno {$deleted} souborů.");

        return self::SUCCESS;
    }
}
