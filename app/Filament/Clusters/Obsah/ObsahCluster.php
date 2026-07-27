<?php

namespace App\Filament\Clusters\Obsah;

use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class ObsahCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Obsah';

    protected static ?int $navigationSort = 40;

    /**
     * The website — its pages, navigation, banners, reviews, e-mail templates and
     * the media library behind them — is edited by administrators. Staff scoped to
     * their own work never publish anything, so the whole cluster stays out of
     * their panel; the resources are already permission-denied to them, and the
     * media library follows this gate ({@see Pages\MediaLibrary::canAccess()}).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->isScopedToOwnWork();
    }
}
