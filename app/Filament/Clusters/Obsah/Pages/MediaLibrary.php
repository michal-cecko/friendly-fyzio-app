<?php

namespace App\Filament\Clusters\Obsah\Pages;

use App\Filament\Clusters\Obsah\ObsahCluster;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use RalphJSmit\Filament\MediaLibrary\Filament\Pages\MediaLibrary as BaseMediaLibrary;

/**
 * Thin wrapper that places the vendor Media Library page inside the Obsah cluster.
 *
 * The vendor plugin has no cluster hook and reads slug/navigation visibility from
 * its own config via HasPageConfiguration, so we override those here and hide the
 * plugin's own (top-level) page via ->registerNavigation(false) in the panel.
 */
class MediaLibrary extends BaseMediaLibrary
{
    protected static ?string $cluster = ObsahCluster::class;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'knihovna-medii';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    /**
     * The one Obsah page a therapist or lecturer holds a permission for, so it
     * is what kept the cluster in their sidebar. It follows the cluster's rule
     * instead — closing the page as well as the navigation entry.
     */
    public static function canAccess(): bool
    {
        return ObsahCluster::canAccess() && parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return 'Knihovna médií';
    }

    /**
     * The vendor page has no title of its own, so Filament derives "Media
     * Library" from the class name — name it in Czech instead.
     */
    public function getTitle(): string|Htmlable
    {
        return 'Knihovna médií';
    }
}
