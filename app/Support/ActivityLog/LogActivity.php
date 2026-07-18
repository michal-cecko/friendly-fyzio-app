<?php

namespace App\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;

/**
 * Thin wrapper over spatie's activity() helper for recording semantic, domain
 * events (e-mail sent, reservation confirmed, payment requested, bulk delete…)
 * that are not plain attribute changes. The payload lands in the `properties`
 * column; `attribute_changes` stays null for these events.
 */
class LogActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        ?Model $subject,
        string $description,
        array $properties = [],
        ?Model $causer = null,
    ): void {
        $logger = activity()
            ->event($event)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $causer ??= auth()->user();

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        $logger->log($description);
    }
}
