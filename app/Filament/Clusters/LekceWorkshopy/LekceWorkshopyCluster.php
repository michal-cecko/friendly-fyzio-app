<?php

namespace App\Filament\Clusters\LekceWorkshopy;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class LekceWorkshopyCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Lekce a workshopy';

    protected static ?int $navigationSort = 30;
}
