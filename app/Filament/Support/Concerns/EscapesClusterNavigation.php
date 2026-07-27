<?php

namespace App\Filament\Support\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

/**
 * Lifts a clustered resource out of its cluster in the sidebar for staff whose
 * panel is narrowed to their own work.
 *
 * A cluster earns its keep when it groups several things an administrator moves
 * between; for a therapist or a lecturer, who reaches exactly one or two of its
 * resources, it is an extra click in front of a single page. The resource keeps
 * its cluster — URLs, breadcrumbs and the cluster's own page are untouched — only
 * the sidebar entry moves up a level, so the item is sorted into the top level
 * explicitly rather than by its position inside the cluster.
 *
 * @see User::isScopedToOwnWork()
 */
trait EscapesClusterNavigation
{
    public static function registerNavigationItems(): void
    {
        if (! static::shouldEscapeClusterNavigation()) {
            parent::registerNavigationItems();

            return;
        }

        if (! static::shouldRegisterNavigation()) {
            return;
        }

        if (! static::canAccess()) {
            return;
        }

        $sort = static::getEscapedNavigationSort();

        Filament::getCurrentOrDefaultPanel()->navigationItems(array_map(
            fn (NavigationItem $item): NavigationItem => $item->sort($sort),
            static::getNavigationItems(),
        ));
    }

    public static function shouldEscapeClusterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isScopedToOwnWork();
    }

    /**
     * Where the promoted item sits among the top-level entries. Defaults to the
     * slot the cluster itself occupied, which keeps the sidebar's running order
     * the same as an administrator's.
     */
    public static function getEscapedNavigationSort(): ?int
    {
        return static::getCluster()::getNavigationSort();
    }
}
