<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\Breadcrumbs\CourseAncestry;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Retraces a course-domain record's parent chain in its breadcrumbs so it reads
 * as a drill-down — Kurzy → kurz → série → přihláška — rather than dead-ending
 * at the flat list of its own resource. The record's own list crumb is swapped
 * out for its ancestors (see {@see CourseAncestry}); the record title and the
 * page's own crumb after it are left exactly as Filament built them.
 *
 * Apply to any View/Edit page whose record {@see CourseAncestry::for()} knows.
 */
trait HasCourseBreadcrumbs
{
    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();

        $ancestors = CourseAncestry::for($this->getRecord());

        if ($ancestors === []) {
            return $breadcrumbs;
        }

        $listUrl = $this->getResourceUrl();
        $trail = [];

        foreach ($breadcrumbs as $url => $label) {
            if ($url === $listUrl) {
                foreach ($ancestors as [$resource, $record, $crumbLabel]) {
                    self::appendAncestorCrumb($trail, $resource, $record, $crumbLabel);
                }

                continue;
            }

            if (is_int($url)) {
                $trail[] = $label;

                continue;
            }

            $trail[$url] = $label;
        }

        return $trail;
    }

    /**
     * Link a parent record to its detail page, falling back to its edit page and
     * finally to plain text when the viewer may open neither.
     *
     * @param  array<int|string, string>  $trail
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    private static function appendAncestorCrumb(array &$trail, string $resource, Model $record, string $label): void
    {
        $url = match (true) {
            $resource::hasPage('view') && $resource::canView($record) => $resource::getUrl('view', ['record' => $record]),
            $resource::hasPage('edit') && $resource::canEdit($record) => $resource::getUrl('edit', ['record' => $record]),
            default => null,
        };

        if ($url === null) {
            $trail[] = $label;

            return;
        }

        $trail[$url] = $label;
    }
}
