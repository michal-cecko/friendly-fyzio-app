<?php

namespace App\Filament\Clusters\Kurzy;

use App\Filament\Support\Concerns\EscapesClusterNavigation;
use App\Filament\Support\Concerns\RestrictedToLecturers;
use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class KurzyCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Kurzy';

    protected static ?int $navigationSort = 20;

    /**
     * Everything in here belongs to whoever teaches it, so the cluster has no
     * reason to exist for someone who does not ({@see RestrictedToLecturers}).
     */
    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }

    /**
     * A lecturer reaches two of these resources — Kurzy and Lekce — and both
     * promote themselves to the sidebar's top level
     * ({@see EscapesClusterNavigation}), so the cluster entry that would wrap
     * them is dropped.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return ! static::isNarrowedToOwnOfferings() && parent::shouldRegisterNavigation();
    }

    public static function shouldRegisterSubNavigation(): bool
    {
        return ! static::isNarrowedToOwnOfferings() && parent::shouldRegisterSubNavigation();
    }

    protected static function isNarrowedToOwnOfferings(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isScopedToOwnWork();
    }
}
