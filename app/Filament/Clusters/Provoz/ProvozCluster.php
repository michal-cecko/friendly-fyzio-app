<?php

namespace App\Filament\Clusters\Provoz;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class ProvozCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Provoz';

    protected static ?int $navigationSort = 10;
}
