<?php

namespace App\Filament\Clusters\Kurzy;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class KurzyCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Kurzy';

    protected static ?int $navigationSort = 20;
}
