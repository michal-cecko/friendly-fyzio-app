<?php

namespace App\Filament\Clusters\System\Resources;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as BaseRoleResource;

/**
 * Suppresses Filament Shield's built-in Roles UI.
 *
 * Shield only registers its own RoleResource when the panel has no resource
 * whose class name contains "\RoleResource" (see Utils::isResourcePublished).
 * Because this resource is auto-discovered before the plugin registers, its
 * mere presence prevents the Shield page from appearing. Denying access keeps
 * the routes locked down too. Roles are still managed programmatically (seeders,
 * `shield:*` Artisan commands).
 */
class RoleResource extends BaseRoleResource
{
    public static function canAccess(): bool
    {
        return false;
    }
}
