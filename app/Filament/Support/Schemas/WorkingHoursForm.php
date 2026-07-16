<?php

namespace App\Filament\Support\Schemas;

use App\Models\Room;
use App\Models\TherapistProfile;
use App\Models\TherapistWorkBlock;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Form fields for a therapist's dated working block
 * (App\Models\TherapistWorkBlock). Used by the calendar's working-hours mode.
 * Creation may repeat the block weekly (every / odd / even weeks), which
 * materializes one row per date via WorkBlockGenerator.
 */
class WorkingHoursForm
{
    /**
     * Create form: one date + optional repeat pattern.
     *
     * @return array<int, Component>
     */
    public static function components(?string $lockedRoomId = null): array
    {
        return [
            self::therapistSelect(),
            self::roomSelect($lockedRoomId),
            DatePicker::make('work_date')
                ->label('Datum')
                ->native(false)
                ->displayFormat('d. m. Y')
                ->required(),
            TimePicker::make('start_time')
                ->label('Od')
                ->seconds(false)
                ->required(),
            self::endTimePicker(),
            Select::make('repeat')
                ->label('Opakování')
                ->options([
                    'none' => 'Neopakuje se',
                    'weekly' => 'Každý týden',
                    'odd' => 'Lichý týden',
                    'even' => 'Sudý týden',
                ])
                ->default('none')
                ->selectablePlaceholder(false)
                ->live(),
            DatePicker::make('repeat_until')
                ->label('Opakovat do')
                ->native(false)
                ->displayFormat('d. m. Y')
                ->visible(fn (Get $get): bool => ($get('repeat') ?? 'none') !== 'none')
                ->afterOrEqual('work_date')
                ->helperText('Prázdné = bez konce, termíny se generují průběžně ~6 měsíců dopředu.'),
        ];
    }

    /**
     * Edit form for a single existing occurrence — no repeat fields.
     *
     * @return array<int, Component>
     */
    public static function occurrence(?string $lockedRoomId = null, ?string $ignoreBlockId = null): array
    {
        return [
            self::therapistSelect(),
            self::roomSelect($lockedRoomId),
            DatePicker::make('work_date')
                ->label('Datum')
                ->native(false)
                ->displayFormat('d. m. Y')
                ->required(),
            TimePicker::make('start_time')
                ->label('Od')
                ->seconds(false)
                ->required(),
            self::endTimePicker($ignoreBlockId),
        ];
    }

    protected static function therapistSelect(): Select
    {
        return Select::make('therapist_id')
            ->label('Terapeut')
            ->options(fn (): array => TherapistProfile::query()
                ->with('user')
                ->get()
                ->mapWithKeys(fn (TherapistProfile $therapist): array => [
                    $therapist->getKey() => $therapist->user?->name ?? '—',
                ])
                ->all())
            ->searchable()
            ->required();
    }

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

    /**
     * End-time picker carrying the overlap guard: a therapist may not have two
     * overlapping blocks on one date (regardless of room). Repeated creation
     * skips conflicting dates instead, so the guard only fires for the block
     * being created/edited itself.
     */
    protected static function endTimePicker(?string $ignoreBlockId = null): TimePicker
    {
        return TimePicker::make('end_time')
            ->label('Do')
            ->seconds(false)
            ->required()
            ->after('start_time')
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $ignoreBlockId): void {
                $therapistId = $get('therapist_id');
                $workDate = $get('work_date');
                $startTime = $get('start_time');

                if (blank($therapistId) || blank($workDate) || blank($startTime) || blank($value)) {
                    return;
                }

                $overlaps = TherapistWorkBlock::overlapsQuery($therapistId, $workDate, $startTime, $value)
                    ->when($ignoreBlockId, fn ($query) => $query->whereKeyNot($ignoreBlockId))
                    ->exists();

                if ($overlaps) {
                    $fail('Terapeut už má v tomto čase pracovní dobu.');
                }
            });
    }
}
