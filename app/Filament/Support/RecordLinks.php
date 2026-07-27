<?php

namespace App\Filament\Support;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Deep links to a record's own resource page, resolved against what the viewer
 * is actually allowed to open.
 *
 * Prefer this over a hardcoded `getUrl('edit')` anywhere a link is rendered for
 * everyone on staff: read-only viewers (therapists, lecturers) hold view rights
 * on plenty of records they may not change, and an edit link hands them a 403
 * instead of the detail page they wanted.
 */
final class RecordLinks
{
    /**
     * A record's detail (infolist) page, falling back to its edit page when the
     * resource has no detail page or the viewer may only edit, and to null when
     * they may open neither.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    public static function detailUrl(string $resource, Model $record): ?string
    {
        return match (true) {
            $resource::hasPage('view') && $resource::canView($record) => $resource::getUrl('view', ['record' => $record]),
            $resource::hasPage('edit') && $resource::canEdit($record) => $resource::getUrl('edit', ['record' => $record]),
            default => null,
        };
    }
}
