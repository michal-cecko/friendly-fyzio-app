<?php

namespace App\Filament\Support\Schemas;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\Room;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Component;

/**
 * Form fields for room blockings (App\Models\RoomBlocking). The calendar uses
 * fixed one-time vs recurring field sets (rather than a live toggle) because the
 * mode is decided by which action opened the modal.
 */
class BlockingForm
{
    /**
     * One-off blocking on concrete dates/times (is_recurring = false).
     *
     * @return array<int, Component>
     */
    public static function oneTime(?string $lockedRoomId = null): array
    {
        return [
            self::roomSelect($lockedRoomId),
            DateTimePicker::make('start_at')
                ->label('Začátek')
                ->seconds(false)
                ->native(false)
                ->required(),
            DateTimePicker::make('end_at')
                ->label('Konec')
                ->seconds(false)
                ->native(false)
                ->required()
                ->after('start_at'),
            self::reasonInput(),
        ];
    }

    /**
     * Recurring weekly blocking (is_recurring = true).
     *
     * @return array<int, Component>
     */
    public static function recurring(?string $lockedRoomId = null): array
    {
        return [
            self::roomSelect($lockedRoomId),
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
            self::reasonInput(),
        ];
    }

    /**
     * Room picker. When $lockedRoomId is given (the calendar is scoped to one
     * room) the select is preset and disabled but still dehydrated, so the
     * fixed room saves while staying read-only.
     */
    protected static function roomSelect(?string $lockedRoomId = null): Select
    {
        $select = Select::make('room_id')
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
            $select->default($lockedRoomId)->disabled()->dehydrated();
        }

        return $select;
    }

    protected static function reasonInput(): TextInput
    {
        return TextInput::make('reason')
            ->label('Důvod')
            ->maxLength(255);
    }
}
