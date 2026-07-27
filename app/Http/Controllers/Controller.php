<?php

namespace App\Http\Controllers;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    /**
     * Whether the current user may preview unpublished public content.
     */
    protected function canPreview(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isStaff();
    }

    /**
     * The Filament edit URL for the record backing the current public page, or
     * null when the viewer may not edit it. Drives the staff-only "edit this
     * page" link in the public site header.
     *
     * Authorized through the resource rather than the model policy: resources
     * layered on a shared model narrow it further (a therapist may update users,
     * but only admins may edit a staff account), and offering a link that lands
     * on a 403 is worse than offering none.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function adminEditUrl(Model $record, string $resource): ?string
    {
        return $resource::hasPage('edit') && $resource::canEdit($record)
            ? $resource::getUrl('edit', ['record' => $record])
            : null;
    }
}
