<?php

namespace App\Filament\Clusters\Finance;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * Finance is cross-cutting: payments/invoices reference payables from Provoz,
 * Kurzy and LekceWorkshopy, so the money records get their own home — payments,
 * invoices, cash receipts and numbering series.
 */
class FinanceCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Finance';

    protected static ?int $navigationSort = 12;
}
