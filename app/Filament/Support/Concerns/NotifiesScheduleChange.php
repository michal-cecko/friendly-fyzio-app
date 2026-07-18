<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\Schemas\NotifyParticipantsToggle;
use App\Support\Enrollments\NotifyScheduleChange;
use App\Support\Enrollments\OfferScheduleSnapshot;

/**
 * Shared behaviour for the three offer Edit pages (course lesson, one-time lesson,
 * workshop): when staff change a session's date/time/room and leave the
 * "notify participants" toggle on ({@see NotifyParticipantsToggle}),
 * e-mail the enrolled participants and the instructor. The old term is snapshotted
 * before the save and compared after, so nothing is sent for an unrelated edit.
 *
 * The using class must be a Filament EditRecord and list the schedule columns via
 * {@see scheduleAttributes()}.
 */
trait NotifiesScheduleChange
{
    /** @var array<string, string> */
    protected array $scheduleSnapshot = [];

    /**
     * The columns whose change counts as a schedule change (date, times, room).
     *
     * @return array<int, string>
     */
    abstract protected function scheduleAttributes(): array;

    protected function beforeSave(): void
    {
        $this->scheduleSnapshot = OfferScheduleSnapshot::capture($this->record);
    }

    protected function afterSave(): void
    {
        if (! data_get($this->data, 'notify_participants', true)) {
            return;
        }

        if (! $this->record->wasChanged($this->scheduleAttributes())) {
            return;
        }

        app(NotifyScheduleChange::class)($this->record, $this->scheduleSnapshot);
    }
}
