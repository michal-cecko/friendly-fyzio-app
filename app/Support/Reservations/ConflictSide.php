<?php

namespace App\Support\Reservations;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Pages\Calendar;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\RoomBlocking;
use App\Models\TherapistWorkBlock;

/**
 * One party to a {@see Conflict}, flattened to what the UI needs: what it is,
 * what to call it, when it runs, and where to go to fix it.
 *
 * Flattening is deliberate — a conflict pairs four unrelated models, and the
 * cards that render them should not have to know which. The admin link is a
 * method rather than a property because resolving a Filament URL needs a bound
 * panel, and conflicts are also computed outside a panel request (unit tests,
 * console). Work blocks and room blockings have no resource of their own, so
 * they deep-link the calendar's working-hours tab on the right date and room.
 */
final class ConflictSide
{
    private function __construct(
        public readonly string $kind,       // reservation | lesson | workBlock | blocking
        public readonly string $label,
        public readonly string $time,       // '09:00–10:00'
        public readonly ?string $recordId,
        public readonly string $date,       // 'Y-m-d'
        public readonly ?string $roomId,
        public readonly ?string $therapistId,
    ) {}

    public static function forReservation(Reservation $reservation): self
    {
        return new self(
            kind: 'reservation',
            label: $reservation->client?->name ?? 'Rezervace',
            time: self::timeLabel(Slot::toMinutes($reservation->start_time), Slot::toMinutes($reservation->end_time)),
            recordId: (string) $reservation->getKey(),
            date: $reservation->reservation_date->toDateString(),
            roomId: $reservation->room_id,
            therapistId: $reservation->therapist_id,
        );
    }

    /**
     * A lesson of a série reads as its course, a standalone one as its own name —
     * the same naming the calendar uses for these cards.
     */
    public static function forLesson(Lesson $lesson, ?string $therapistId): self
    {
        return new self(
            kind: 'lesson',
            label: $lesson->series?->course?->name ?? $lesson->series?->name ?? (string) $lesson->name,
            time: self::timeLabel(Slot::toMinutes($lesson->start_time), Slot::toMinutes($lesson->end_time)),
            recordId: (string) $lesson->getKey(),
            date: $lesson->lesson_date->toDateString(),
            roomId: $lesson->room_id,
            therapistId: $therapistId,
        );
    }

    public static function forWorkBlock(TherapistWorkBlock $block): self
    {
        $therapist = $block->therapist?->user?->name;

        return new self(
            kind: 'workBlock',
            label: trim('Pracovní doba'.($therapist !== null ? ' · '.$therapist : '')),
            time: self::timeLabel(Slot::toMinutes($block->start_time), Slot::toMinutes($block->end_time)),
            recordId: (string) $block->getKey(),
            date: $block->work_date->toDateString(),
            roomId: $block->room_id,
            therapistId: $block->therapist_id,
        );
    }

    /**
     * A blocking carries the date and clipped minutes of the occurrence rather
     * than its own columns: a recurring row has no date, and a one-off row may
     * span several days.
     */
    public static function forBlocking(RoomBlocking $blocking, string $date, int $startMin, int $endMin): self
    {
        return new self(
            kind: 'blocking',
            label: $blocking->reason ?: 'Blokace',
            time: self::timeLabel($startMin, $endMin),
            recordId: (string) $blocking->getKey(),
            date: $date,
            roomId: $blocking->room_id,
            therapistId: null,
        );
    }

    public function matches(string $kind, string $recordId): bool
    {
        return $this->kind === $kind && $this->recordId === $recordId;
    }

    /**
     * Identity that survives repetition across dates. A work block is a fresh row
     * per day, so the thing that actually recurs is "this therapist, this room";
     * everything else is its own record.
     */
    public function recurringId(): string
    {
        return $this->kind === 'workBlock'
            ? $this->kind.':'.$this->therapistId.':'.$this->roomId
            : $this->kind.':'.$this->recordId;
    }

    public function url(): ?string
    {
        return match ($this->kind) {
            'reservation' => $this->recordId === null ? null : ReservationResource::getUrl('view', ['record' => $this->recordId]),
            'lesson' => $this->recordId === null ? null : LessonResource::getUrl('view', ['record' => $this->recordId]),
            'workBlock', 'blocking' => Calendar::getUrl().'?'.http_build_query(array_filter([
                'mode' => 'template',
                'date' => $this->date,
                'room' => $this->roomId,
            ])),
            default => null,
        };
    }

    private static function timeLabel(int $startMin, int $endMin): string
    {
        return Slot::toTime($startMin).'–'.Slot::toTime($endMin);
    }
}
