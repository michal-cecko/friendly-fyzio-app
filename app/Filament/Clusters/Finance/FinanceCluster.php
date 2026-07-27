<?php

namespace App\Filament\Clusters\Finance;

use App\Filament\Support\Concerns\EscapesClusterNavigation;
use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * Finance is cross-cutting: payments/invoices reference payables from Provoz,
 * the Kurzy cluster, so the money records get their own home — payments,
 * invoices, cash receipts and numbering series.
 */
class FinanceCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Finance';

    protected static ?int $navigationSort = 12;

    /**
     * Invoices, cash receipts and numbering series are an administrative
     * concern, so staff scoped to their own work reach exactly one resource in
     * here — Platby, which promotes itself to the sidebar's top level
     * ({@see EscapesClusterNavigation}). A cluster entry wrapping a single
     * item would only add a click.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return ! static::isNarrowedToPayments() && parent::shouldRegisterNavigation();
    }

    public static function shouldRegisterSubNavigation(): bool
    {
        return ! static::isNarrowedToPayments() && parent::shouldRegisterSubNavigation();
    }

    protected static function isNarrowedToPayments(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isScopedToOwnWork();
    }
}
