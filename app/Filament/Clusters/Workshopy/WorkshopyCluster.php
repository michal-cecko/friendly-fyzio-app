<?php

namespace App\Filament\Clusters\Workshopy;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class WorkshopyCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Workshopy';

    protected static ?int $navigationSort = 21;
}
