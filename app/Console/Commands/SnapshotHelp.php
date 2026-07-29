<?php

namespace App\Console\Commands;

use App\Filament\Support\Help\HelpRepository;
use App\Filament\Support\Help\HelpVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Archives the current manual as a browsable version under
 * resources/help-versions, stamped with the commit it was taken at.
 *
 * Run by hand when a batch of documentation edits is worth keeping — not on
 * every commit. The snapshot is a verbatim copy of the article tree, so the
 * admin can page through an old version exactly as it read at the time; it is
 * committed to the repository, which is what makes it survive into the
 * production image (no git binary there to reconstruct it from).
 */
class SnapshotHelp extends Command
{
    protected $signature = 'help:snapshot
        {--id= : Version id, and URL segment. Defaults to the commit date (2026-07-29).}
        {--force : Overwrite a snapshot that already carries this id.}';

    protected $description = 'Uloží aktuální nápovědu jako archivní verzi označenou datem commitu.';

    public function handle(HelpVersions $versions, HelpRepository $live): int
    {
        $source = $live->root();

        if (! File::isDirectory($source)) {
            $this->error("Nápověda nebyla nalezena v {$source}.");

            return self::FAILURE;
        }

        $commit = $this->commit();
        $id = $this->id($commit);
        $target = $versions->path().DIRECTORY_SEPARATOR.$id;

        if (File::isDirectory($target) && ! $this->option('force')) {
            $this->error("Verze {$id} už existuje. Spusťte s --force pro přepsání, nebo zvolte --id.");

            return self::FAILURE;
        }

        if ($commit['dirty']) {
            $this->warn('Pozor: v resources/help jsou necommitnuté změny — snímek proto neodpovídá přesně commitu '.($commit['short'] ?? '?').'.');
        }

        File::deleteDirectory($target);
        File::ensureDirectoryExists($target);
        File::copyDirectory($source, $target);

        $repository = new HelpRepository($target);
        $sections = $repository->sections();

        File::put(
            $target.DIRECTORY_SEPARATOR.HelpVersions::MANIFEST,
            json_encode([
                'id' => $id,
                'date' => $commit['date']->toDateString(),
                'commit' => $commit['short'],
                'subject' => $commit['subject'],
                'sections' => $sections->count(),
                'topics' => $repository->topics()->count(),
                'generated_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );

        $this->info("Verze {$id} uložena do {$target}.");
        $this->line('  '.$sections->count().' sekcí, '.$repository->topics()->count().' článků'
            .($commit['short'] !== null ? ', commit '.$commit['short'] : ''));
        $this->comment('Nezapomeňte snímek commitnout — do produkčního image se dostane jen z repozitáře.');

        return self::SUCCESS;
    }

    /**
     * Facts about HEAD. Git is present on developer machines and in the build
     * image but not at runtime, so a missing binary is not an error here — the
     * snapshot simply falls back to today's date and carries no commit.
     *
     * @return array{date: Carbon, short: ?string, subject: ?string, dirty: bool}
     */
    protected function commit(): array
    {
        $log = Process::path(base_path())->run('git log -1 --format=%h%x1f%cI%x1f%s');

        if (! $log->successful()) {
            $this->warn('Git není k dispozici — verze se označí dnešním datem a bez commitu.');

            return ['date' => today(), 'short' => null, 'subject' => null, 'dirty' => false];
        }

        [$short, $date, $subject] = array_pad(explode("\x1f", trim($log->output())), 3, '');

        $status = Process::path(base_path())->run('git status --porcelain -- '.escapeshellarg(app(HelpRepository::class)->root()));

        return [
            'date' => filled($date) ? Carbon::parse($date) : today(),
            'short' => filled($short) ? $short : null,
            'subject' => filled($subject) ? $subject : null,
            'dirty' => $status->successful() && filled(trim($status->output())),
        ];
    }

    /**
     * @param  array{date: Carbon, short: ?string, subject: ?string, dirty: bool}  $commit
     */
    protected function id(array $commit): string
    {
        $id = trim((string) ($this->option('id') ?? ''));

        return $id !== '' ? $id : $commit['date']->toDateString();
    }
}
