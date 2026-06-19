<?php

namespace App\Filament\Support\Schemas;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\Room;
use App\Models\TherapistProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Component;

/**
 * Form fields for a therapist's recurring weekly working block
 * (App\Models\TherapistWeeklySchedule). Used by the calendar's template mode.
 */
class WorkingHoursForm
{
    /**
     * @return array<int, Component>
     */
    public static function components(?string $lockedRoomId = null): array
    {
        $roomSelect = Select::make('room_id')
            ->label('Místnost')
            ->options(fn (): array => Room::query()
                ->with('building')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Room $room): array => [
                    $room->getKey() => $room->building
                        ? "{$room->name} · {$room->building->name}"
                        : $room->name,
                ])
                ->all())
            ->searchable()
            ->required();

        if ($lockedRoomId !== null) {
            $roomSelect->default($lockedRoomId)->disabled()->dehydrated();
        }

        return [
            Select::make('therapist_id')
                ->label('Terapeut')
                ->options(fn (): array => TherapistProfile::query()
                    ->with('user')
                    ->get()
                    ->mapWithKeys(fn (TherapistProfile $therapist): array => [
                        $therapist->getKey() => $therapist->user?->name ?? '—',
                    ])
                    ->all())
                ->searchable()
                ->required(),
            $roomSelect,
            Select::make('day_of_week')
                ->label('Den v týdnu')
                ->options(DayOfWeek::class)
                ->required(),
            Select::make('week_type')
                ->label('Typ týdne')
                ->options(WeekType::class)
                ->default(WeekType::All->value)
                ->required(),
            TimePicker::make('start_time')
                ->label('Od')
                ->seconds(false)
                ->required(),
            TimePicker::make('end_time')
                ->label('Do')
                ->seconds(false)
                ->required()
                ->after('start_time'),
        ];
    }
}
