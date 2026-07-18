<?php

namespace App\Filament\Clusters\Lekce;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class LekceCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Jednorázové lekce';

    protected static ?int $navigationSort = 22;
}
