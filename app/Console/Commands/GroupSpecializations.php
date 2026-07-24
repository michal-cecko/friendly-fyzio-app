<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\Specialization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('specializations:group {--dry-run : Preview the changes without saving}')]
#[Description('Assign catalog specializations to their service (name match + editable overrides). Idempotent; safe to run on production after migrating.')]
class GroupSpecializations extends Command
{
    /**
     * Best-guess mapping of specialization name → service name for cases where
     * the two names differ. Exact-name matches (e.g. "Bylinná napářka") are
     * handled automatically and need no entry here. Edit freely and re-run.
     *
     * @var array<string, string>
     */
    protected array $overrides = [
        // Real team (RealDataSeeder)
        'Urogynekologická fyzioterapie' => 'Terapie pánevního dna',
        'Terapie jizev' => 'Vstupní vyšetření pohybového aparátu',
        'Onkologická fyzioterapie' => 'Vstupní vyšetření pohybového aparátu',
        'Onkologická fyzioterapie – rakovina prsu' => 'Vstupní vyšetření pohybového aparátu',
        'Terapie čelistního kloubu' => 'Vstupní vyšetření pohybového aparátu',
        'Fyzioterapie nohy' => 'Vstupní vyšetření pohybového aparátu',
        'SM systém' => 'Vstupní vyšetření pohybového aparátu',
        'Masáže žen a dětí' => 'Masáže miminek a dětí',
        // 'Access Bars' is intentionally left unmapped — no matching service.

        // Demo data (DemoSeeder)
        'Pánevní dno' => 'Terapie pánevního dna',
        'Těhotenství a porod' => 'Těhotenská fyzioterapie',
        'Dětská fyzioterapie' => 'Masáže miminek a dětí',
        'Pohybový aparát' => 'Vstupní vyšetření pohybového aparátu',
        'Ortopedická rehabilitace' => 'Vstupní vyšetření pohybového aparátu',
        'Sport' => 'Vstupní vyšetření pohybového aparátu',
        'Jóga' => 'Vstupní vyšetření pohybového aparátu',
        'Pilates' => 'Vstupní vyšetření pohybového aparátu',
        'Relaxační masáže' => 'Klasická masáž',
        'Lymfodrenáž' => 'Lymfatické masáže',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Index services by a normalized (lower-cased, trimmed) name.
        $servicesByName = Service::query()->get()
            ->keyBy(fn (Service $service): string => Str::lower(trim($service->name)));

        $grouped = 0;
        $unmapped = [];

        foreach (Specialization::query()->orderBy('name')->get() as $specialization) {
            $targetName = $this->overrides[$specialization->name] ?? $specialization->name;
            $service = $servicesByName->get(Str::lower(trim($targetName)));

            if ($service === null) {
                $unmapped[] = $specialization->name;

                continue;
            }

            if ($specialization->service_id !== $service->getKey()) {
                if (! $dryRun) {
                    $specialization->update(['service_id' => $service->getKey()]);
                }

                $this->line(sprintf('  %s → %s', $specialization->name, $service->name));
            }

            $grouped++;
        }

        $this->newLine();
        $this->info(sprintf('%s%d specialization(s) grouped under a service.', $dryRun ? '[dry-run] ' : '', $grouped));

        if ($unmapped !== []) {
            $this->warn(sprintf('%d left ungrouped (no matching service): %s', count($unmapped), implode(', ', $unmapped)));
        }

        return self::SUCCESS;
    }
}
