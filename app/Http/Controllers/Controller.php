<?php

namespace App\Http\Controllers;

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
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function adminEditUrl(Model $record, string $resource): ?string
    {
        return auth()->user()?->can('update', $record)
            ? $resource::getUrl('edit', ['record' => $record])
            : null;
    }
}
