<?php

namespace App\Console\Commands;

use App\Support\Lessons\ReleaseFreeSpots;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lessons:release-free-spots')]
#[Description('Nabídne volná místa na lekcích čekací listině a potom veřejnosti jako jednorázový vstup')]
class ReleaseLessonFreeSpots extends Command
{
    public function handle(ReleaseFreeSpots $release): int
    {
        $result = $release();

        $this->info(sprintf(
            'Osloveno čekajících: %d · zveřejněno lekcí: %d · staženo z prodeje: %d',
            $result['invited'],
            $result['released'],
            $result['withdrawn'],
        ));

        return self::SUCCESS;
    }
}
