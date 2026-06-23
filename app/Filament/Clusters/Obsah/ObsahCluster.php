<?php

namespace App\Filament\Clusters\Obsah;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class ObsahCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Obsah';

    protected static ?int $navigationSort = 40;
}
