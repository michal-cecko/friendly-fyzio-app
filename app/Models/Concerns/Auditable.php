<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records an activity-log entry for every create / update / delete / restore of
 * the model, capturing the full attribute set (including UUIDs and foreign
 * keys). Updates keep a changed-only before/after diff; create and delete keep
 * the complete snapshot, so a deleted or corrupted record can still be traced
 * end to end.
 *
 * Each entry's description is the record's human-readable {@see logTitle()},
 * stored at write time so the log stays readable even after the record is gone.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['password', 'remember_token'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $this->logTitle();
    }

    /**
     * A human-readable label for this record in the activity log. Models with no
     * obvious name/title should override this with something meaningful.
     */
    public function logTitle(): string
    {
        $title = $this->name
            ?? $this->title
            ?? $this->invoice_number
            ?? $this->code
            ?? null;

        if (is_string($title) && $title !== '') {
            return $title;
        }

        return class_basename($this).' #'.Str::of((string) $this->getKey())->substr(0, 8);
    }
}
