<?php

namespace App\Filament\Widgets;

use App\Enums\DayOfWeek;
use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Enums\WeekType;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\Schemas\BlockingForm;
use App\Filament\Support\Schemas\WorkingHoursForm;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\TherapistWeeklySchedule;
use App\Models\User;
use App\Notifications\ReservationNotification;
use App\Notifications\ReservationTemplateNotification;
use App\Support\CalendarAvailability;
use App\Support\Reservations\ReservationChangeSnapshot;
use App\Support\Reservations\ReservationSummary;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Saade\FilamentFullCalendar\Actions\CreateAction as FullCalendarCreateAction;
use Saade\FilamentFullCalendar\Actions\DeleteAction as FullCalendarDeleteAction;
use Saade\FilamentFullCalendar\Actions\EditAction as FullCalendarEditAction;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ReservationCalendar extends FullCalendarWidget
{
    /** Indigo accent/tint used for blocking events (matches the "Blokace" legend). */
    private const BLOCKING_COLORS = ['#6366F1', '#EEF2FF'];

    /**
     * When set, the calendar is scoped to a single room: every query is filtered
     * by this room, the room filter UI is hidden, and the add/edit blocking and
     * working-hours modals lock to it. Null on the global calendar page.
     */
    public ?Room $room = null;

    /** Calendar mode: 'reservations' (real bookings) or 'template' (recurring week). */
    #[Url(as: 'mode')]
    public string $mode = 'reservations';

    /** @var array<int, string> */
    #[Url(as: 'therapists')]
    public array $therapistIds = [];

    #[Url(as: 'q')]
    public string $search = '';

    /** Whether the collapsible filter selects are expanded. */
    public bool $showFilters = false;

    /** Date shown in the calendar (Y-m-d), persisted to the URL. */
    #[Url(as: 'date')]
    public ?string $calendarDate = null;

    /** Template mode: which week parity to show (all / odd / even). */
    #[Url(as: 'week')]
    public string $templateWeekType = 'all';

    /** Template mode: restrict to a single room (null = all rooms). */
    #[Url(as: 'room')]
    public ?string $templateRoomId = null;

    /** Reservations sidebar: the month shown in the mini calendar (Y-m). */
    #[Url(as: 'm')]
    public ?string $sidebarMonth = null;

    /** Reservations sidebar collapsed state. */
    public bool $sidebarCollapsed = false;

    /** Whether clicking a calendar card selects it (for bulk actions) instead of opening the edit modal. */
    public bool $selectionMode = false;

    /** Template block currently being edited (set on event click before mounting the edit action). */
    public ?string $editingTemplateId = null;

    /**
     * Reservation IDs currently selected for bulk actions. Persists across week navigation.
     *
     * @var array<int, string>
     */
    public array $selectedIds = [];

    /**
     * Calendar filter state, bound to the Filament filters form.
     *
     * @var array{clientIds?: array<int, string>, roomIds?: array<int, string>, statusIds?: array<int, string>, serviceIds?: array<int, string>, trashed?: string}
     */
    #[Url(as: 'filters')]
    public ?array $filterData = [
        'clientIds' => [],
        'roomIds' => [],
        'statusIds' => [],
        'serviceIds' => [],
        'trashed' => 'without',
    ];

    public int $weekCount = 0;

    protected string $view = 'filament.widgets.reservation-calendar';

    /** @var array<int, array{0: string, 1: string}> [accent, tint] per therapist slot. */
    protected array $palette = [
        ['#ED86A3', '#FFF1F4'],
        ['#3B82F6', '#EFF6FF'],
        ['#22C55E', '#F2FDF5'],
        ['#F59E0B', '#FFFBEB'],
        ['#8B5CF6', '#F5F3FF'],
        ['#14B8A6', '#F0FDFA'],
    ];

    /** @var Collection<int, TherapistProfile>|null */
    protected ?Collection $therapistCache = null;

    /**
     * Pre-edit snapshot of the reservation being edited ({{ puvodni_* }} tokens),
     * captured in the edit action's before() hook and consumed in after() so the
     * "reservation changed" e-mail can show the original values next to the new ones.
     *
     * @var array<string, string>
     */
    protected array $reservationChangeSnapshot = [];

    public function getModel(): ?string
    {
        return Reservation::class;
    }

    public function config(): array
    {
        return [
            'headerToolbar' => false,
            'initialView' => 'timeGridWeek',
            'initialDate' => $this->calendarDate ?: now()->toDateString(),
            'firstDay' => 1,
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '20:00:00',
            'slotDuration' => gmdate('H:i:s', Settings::blockMinutes() * 60),
            'slotLabelInterval' => '01:00:00',
            'slotLabelFormat' => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
            'dayHeaderFormat' => ['weekday' => 'short', 'day' => 'numeric', 'omitCommas' => true],
            'titleFormat' => ['year' => 'numeric', 'month' => 'long', 'day' => 'numeric'],
            'allDaySlot' => false,
            'nowIndicator' => $this->mode === 'reservations',
            'expandRows' => true,
            'height' => 'auto',
            'locale' => 'cs',
        ];
    }

    public function isTemplateMode(): bool
    {
        return $this->mode === 'template';
    }

    /**
     * @return Collection<int, TherapistProfile>
     */
    public function therapists(): Collection
    {
        return $this->therapistCache ??= TherapistProfile::query()->with('user')->get();
    }

    /**
     * @return Collection<int, Room>
     */
    public function rooms(): Collection
    {
        return Room::query()->with('building')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Service>
     */
    public function services(): Collection
    {
        return Service::query()->orderBy('name')->get();
    }

    /**
     * Room options keyed by id for the template toolbar select.
     *
     * @return array<string, string>
     */
    public function roomOptions(): array
    {
        return $this->rooms()
            ->mapWithKeys(fn (Room $room): array => [
                $room->getKey() => $room->building ? "{$room->name} · {$room->building->name}" : $room->name,
            ])
            ->all();
    }

    public function mount(?Room $room = null): void
    {
        // Set only when provided (room-scoped View page); the global calendar
        // page mounts the widget without a room.
        if ($room !== null) {
            $this->room = $room;
        }

        // Livewire merges any URL query params over the property default above,
        // so $filterData already carries every key (URL values + defaults).
        $this->filtersForm->fill($this->filterData ?? []);

        $this->sidebarMonth ??= $this->selectedDate()->format('Y-m');
    }

    public function updated(string $property): void
    {
        if ($property === 'mode') {
            // Selected IDs are mode-specific (reservation keys in reservations
            // mode, schedule:/blocking: keys in template mode), so don't carry
            // a stale selection across the toggle.
            $this->selectedIds = [];
        }

        if (in_array($property, ['search', 'mode', 'templateWeekType', 'templateRoomId'], true)) {
            $this->dispatch('filament-fullcalendar--refresh');
        }
    }

    public function resetFilters(): void
    {
        $this->therapistIds = [];
        $this->search = '';
        $this->filtersForm->fill();

        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function hasActiveFilters(): bool
    {
        return filled($this->therapistIds)
            || filled($this->search)
            || $this->activeFilterCount() > 0;
    }

    /**
     * Number of active select filters (the ones inside the collapsible panel).
     */
    public function activeFilterCount(): int
    {
        $count = 0;

        $keys = $this->room
            ? ['clientIds', 'statusIds', 'serviceIds']
            : ['clientIds', 'roomIds', 'statusIds', 'serviceIds'];

        foreach ($keys as $key) {
            if (filled($this->filterData[$key] ?? [])) {
                $count++;
            }
        }

        if (($this->filterData['trashed'] ?? 'without') !== 'without') {
            $count++;
        }

        return $count;
    }

    public function toggleSelectionMode(): void
    {
        $this->selectionMode = ! $this->selectionMode;

        if (! $this->selectionMode) {
            $this->selectedIds = [];
        }

        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function toggleSelection(string $id): void
    {
        $this->selectedIds = in_array($id, $this->selectedIds, true)
            ? array_values(array_diff($this->selectedIds, [$id]))
            : [...$this->selectedIds, $id];

        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];

        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function viewingTrashed(): bool
    {
        return in_array($this->filterData['trashed'] ?? 'without', ['with', 'only'], true);
    }

    /**
     * @return Collection<int, Reservation>
     */
    protected function selectedReservations(): Collection
    {
        if ($this->selectedIds === []) {
            return collect();
        }

        return Reservation::query()
            ->withTrashed()
            ->with('client')
            ->whereIn('id', $this->selectedIds)
            ->get();
    }

    protected function selectedCountLabel(): string
    {
        return 'Počet vybraných rezervací: '.count($this->selectedIds);
    }

    public function cancelSelectedAction(): Action
    {
        return Action::make('cancelSelected')
            ->label('Zrušit rezervace')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('warning')
            ->disabled(fn (): bool => $this->selectedIds === [])
            ->modalHeading('Zrušit vybrané rezervace')
            ->modalDescription(fn (): string => $this->selectedCountLabel())
            ->schema([
                Textarea::make('cancellation_reason')
                    ->label('Důvod zrušení (nepovinné)')
                    ->rows(2),
                Toggle::make('notify_client')
                    ->label('Informovat klienty e-mailem')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                $this->selectedReservations()->each(function (Reservation $reservation) use ($data): void {
                    $reservation->update([
                        'status' => ReservationStatus::Cancelled,
                        'cancellation_reason' => $data['cancellation_reason'] ?? null,
                    ]);

                    if ($data['notify_client'] ?? false) {
                        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationCancelled));
                    }
                });

                Notification::make()->success()->title('Rezervace byly zrušeny')->send();

                $this->clearSelection();
            });
    }

    public function deleteSelectedAction(): Action
    {
        return Action::make('deleteSelected')
            ->label('Smazat')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->disabled(fn (): bool => $this->selectedIds === [])
            ->modalHeading('Smazat vybrané rezervace')
            ->modalDescription(fn (): string => $this->selectedCountLabel())
            ->schema([
                Toggle::make('notify_client')
                    ->label('Informovat klienty e-mailem')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                $this->selectedReservations()->each(function (Reservation $reservation) use ($data): void {
                    if ($data['notify_client'] ?? false) {
                        $reservation->client?->notify(new ReservationNotification($reservation, 'cancelled'));
                    }

                    $reservation->delete();
                });

                Notification::make()->success()->title('Rezervace byly smazány')->send();

                $this->clearSelection();
            });
    }

    public function restoreSelectedAction(): Action
    {
        return Action::make('restoreSelected')
            ->label('Obnovit')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => $this->viewingTrashed())
            ->disabled(fn (): bool => $this->selectedIds === [])
            ->requiresConfirmation()
            ->modalHeading('Obnovit vybrané rezervace')
            ->modalDescription(fn (): string => $this->selectedCountLabel())
            ->action(function (): void {
                Reservation::query()
                    ->onlyTrashed()
                    ->whereIn('id', $this->selectedIds)
                    ->restore();

                Notification::make()->success()->title('Rezervace byly obnoveny')->send();

                $this->clearSelection();
            });
    }

    /**
     * Bulk-delete the items selected in template mode: recurring working-hours
     * schedules (schedule:*) and recurring blockings (blocking:*).
     */
    public function deleteSelectedTemplateAction(): Action
    {
        return Action::make('deleteSelectedTemplate')
            ->label('Smazat')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->disabled(fn (): bool => $this->selectedIds === [])
            ->requiresConfirmation()
            ->modalHeading('Smazat vybrané položky')
            ->modalDescription(fn (): string => 'Počet vybraných položek: '.count($this->selectedIds))
            ->action(function (): void {
                $scheduleIds = [];
                $blockingIds = [];

                foreach ($this->selectedIds as $selectedId) {
                    [$kind, $id] = array_pad(explode(':', $selectedId), 2, null);

                    if ($id === null) {
                        continue;
                    }

                    if ($kind === 'schedule') {
                        $scheduleIds[] = $id;
                    } elseif ($kind === 'blocking') {
                        $blockingIds[] = $id;
                    }
                }

                if ($scheduleIds !== []) {
                    TherapistWeeklySchedule::whereIn('id', $scheduleIds)->delete();
                }

                if ($blockingIds !== []) {
                    RoomBlocking::whereIn('id', $blockingIds)->delete();
                }

                Notification::make()->success()->title('Vybrané položky byly smazány')->send();

                $this->clearSelection();
            });
    }

    /**
     * One-time room blocking, created from the reservations toolbar.
     */
    public function addOneTimeBlockingAction(): Action
    {
        return Action::make('addOneTimeBlocking')
            ->label('Přidat blokaci')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->modalHeading('Přidat jednorázovou blokaci')
            ->schema(BlockingForm::oneTime($this->room?->getKey()))
            ->action(function (array $data): void {
                if ($this->room) {
                    $data['room_id'] = $this->room->getKey();
                }

                RoomBlocking::create([...$data, 'is_recurring' => false]);

                Notification::make()->success()->title('Blokace byla přidána')->send();

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    /**
     * Recurring room blocking, created from the template toolbar.
     */
    public function editOneTimeBlockingAction(): Action
    {
        return Action::make('editOneTimeBlocking')
            ->modalHeading('Upravit blokaci')
            ->fillForm(fn (): array => RoomBlocking::find($this->editingTemplateId)?->only([
                'room_id', 'start_at', 'end_at', 'reason',
            ]) ?? [])
            ->schema(BlockingForm::oneTime($this->room?->getKey()))
            ->extraModalFooterActions([
                Action::make('deleteOneTimeBlocking')
                    ->label('Smazat')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation()
                    ->action(function (): void {
                        RoomBlocking::whereKey($this->editingTemplateId)->delete();

                        Notification::make()->success()->title('Blokace byla smazána')->send();

                        $this->dispatch('filament-fullcalendar--refresh');
                    })
                    ->cancelParentActions(),
            ])
            ->action(function (array $data): void {
                if ($this->room) {
                    $data['room_id'] = $this->room->getKey();
                }

                RoomBlocking::whereKey($this->editingTemplateId)->update([...$data, 'is_recurring' => false]);

                Notification::make()->success()->title('Blokace byla upravena')->send();

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    public function addBlockingAction(): Action
    {
        return Action::make('addBlocking')
            ->label('Přidat blokaci')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->modalHeading('Přidat opakovanou blokaci')
            ->schema(BlockingForm::recurring($this->room?->getKey()))
            ->action(function (array $data): void {
                if ($this->room) {
                    $data['room_id'] = $this->room->getKey();
                }

                RoomBlocking::create([...$data, 'is_recurring' => true]);

                Notification::make()->success()->title('Blokace byla přidána')->send();

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    /**
     * Recurring working-hours block, created from the template toolbar.
     */
    public function addWorkingHoursAction(): Action
    {
        return Action::make('addWorkingHours')
            ->label('Přidat pracovní dobu')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->modalHeading('Přidat pracovní dobu')
            ->schema(WorkingHoursForm::components($this->room?->getKey()))
            ->action(function (array $data): void {
                if ($this->room) {
                    $data['room_id'] = $this->room->getKey();
                }

                TherapistWeeklySchedule::create($data);

                Notification::make()->success()->title('Pracovní doba byla přidána')->send();

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    public function editScheduleAction(): Action
    {
        return Action::make('editSchedule')
            ->modalHeading('Upravit pracovní dobu')
            ->fillForm(fn (): array => TherapistWeeklySchedule::find($this->editingTemplateId)?->only([
                'therapist_id', 'room_id', 'day_of_week', 'week_type', 'start_time', 'end_time',
            ]) ?? [])
            ->schema(WorkingHoursForm::components($this->room?->getKey()))
            ->extraModalFooterActions([
                Action::make('deleteSchedule')
                    ->label('Smazat')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation()
                    ->action(function (): void {
                        TherapistWeeklySchedule::whereKey($this->editingTemplateId)->delete();

                        Notification::make()->success()->title('Pracovní doba byla smazána')->send();

                        $this->dispatch('filament-fullcalendar--refresh');
                    })
                    ->cancelParentActions(),
            ])
            ->action(function (array $data): void {
                if ($this->room) {
                    $data['room_id'] = $this->room->getKey();
                }

                TherapistWeeklySchedule::whereKey($this->editingTemplateId)->update($data);

                Notification::make()->success()->title('Pracovní doba byla upravena')->send();

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    public function editBlockingAction(): Action
    {
        return Action::make('editBlocking')
            ->modalHeading('Upravit blokaci')
            ->fillForm(fn (): array => RoomBlocking::find($this->editingTemplateId)?->only([
                'room_id', 'day_of_week', 'week_type', 'start_time', 'end_time', 'reason',
            ]) ?? [])
            ->schema(BlockingForm::recurring($this->room?->getKey()))
            ->extraModalFooterActions([
                Action::make('deleteBlocking')
                    ->label('Smazat')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation()
                    ->action(function (): void {
                        RoomBlocking::whereKey($this->editingTemplateId)->delete();

                        Notification::make()->success()->title('Blokace byla smazána')->send();

                        $this->dispatch('filament-fullcalendar--refresh');
                    })
                    ->cancelParentActions(),
            ])
            ->action(function (array $data): void {
                if ($this->room) {
                    $data['room_id'] = $this->room->getKey();
                }

                RoomBlocking::whereKey($this->editingTemplateId)->update([...$data, 'is_recurring' => true]);

                Notification::make()->success()->title('Blokace byla upravena')->send();

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filterData')
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->components([
                Select::make('clientIds')
                    ->label('Klient')
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where('role', UserRole::Customer)
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelsUsing(fn (array $values): array => User::query()
                        ->whereIn('id', $values)
                        ->pluck('name', 'id')
                        ->all())
                    ->default([])
                    ->placeholder('Všichni klienti')
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatch('filament-fullcalendar--refresh')),
                Select::make('roomIds')
                    ->label('Místnost')
                    ->hidden((bool) $this->room)
                    ->multiple()
                    ->options(fn (): array => $this->roomOptions())
                    ->default([])
                    ->placeholder('Všechny místnosti')
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatch('filament-fullcalendar--refresh')),
                Select::make('statusIds')
                    ->label('Stav')
                    ->multiple()
                    ->options(ReservationStatus::class)
                    ->default([])
                    ->placeholder('Všechny')
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatch('filament-fullcalendar--refresh')),
                Select::make('serviceIds')
                    ->label('Služba')
                    ->multiple()
                    ->options(fn (): array => $this->services()->pluck('name', 'id')->all())
                    ->default([])
                    ->placeholder('Všechny')
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatch('filament-fullcalendar--refresh')),
                Select::make('trashed')
                    ->label('Smazané')
                    ->options([
                        'without' => 'Bez smazaných',
                        'with' => 'Se smazanými',
                        'only' => 'Pouze smazané',
                    ])
                    ->default('without')
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatch('filament-fullcalendar--refresh')),
            ]);
    }

    /**
     * Apply the shared filter-bar state (therapists, clients, rooms, services,
     * status, search, trashed) to a reservations query.
     */
    protected function applyFilters(Builder $query): Builder
    {
        $clientIds = $this->filterData['clientIds'] ?? [];
        $roomIds = $this->room ? [] : ($this->filterData['roomIds'] ?? []);
        $serviceIds = $this->filterData['serviceIds'] ?? [];
        $statusIds = $this->filterData['statusIds'] ?? [];
        $trashed = $this->filterData['trashed'] ?? 'without';

        return $query
            ->when($this->room, fn (Builder $query) => $query->where('room_id', $this->room->getKey()))
            ->when($this->therapistIds, fn (Builder $query) => $query->whereIn('therapist_id', $this->therapistIds))
            ->when($clientIds, fn (Builder $query) => $query->whereIn('client_id', $clientIds))
            ->when($roomIds, fn (Builder $query) => $query->whereIn('room_id', $roomIds))
            ->when($serviceIds, fn (Builder $query) => $query->whereIn('service_id', $serviceIds))
            ->when($statusIds, fn (Builder $query) => $query->whereIn('status', $statusIds))
            ->when(filled($this->search), function (Builder $query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->whereHas('client', fn (Builder $relation) => $relation->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('service', fn (Builder $relation) => $relation->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->when($trashed === 'with', fn (Builder $query) => $query->withTrashed())
            ->when($trashed === 'only', fn (Builder $query) => $query->onlyTrashed());
    }

    protected function therapistIndex(string $therapistId): int
    {
        $position = $this->therapists()->pluck('id')->values()->search($therapistId);

        return $position === false ? 0 : (int) $position % count($this->palette);
    }

    public function therapistColor(string $therapistId): string
    {
        return $this->palette[$this->therapistIndex($therapistId)][0];
    }

    public function therapistTint(string $therapistId): string
    {
        return $this->palette[$this->therapistIndex($therapistId)][1];
    }

    public function therapistInitials(?string $name): string
    {
        if (blank($name)) {
            return '?';
        }

        $words = array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            fn (string $word): bool => $word !== '' && ! str_ends_with($word, '.'),
        ));

        if ($words === []) {
            return mb_strtoupper(mb_substr($name, 0, 2));
        }

        return mb_strtoupper(
            collect($words)->take(2)->map(fn (string $word): string => mb_substr($word, 0, 1))->implode('')
        );
    }

    public function toggleTherapist(string $id): void
    {
        $this->therapistIds = in_array($id, $this->therapistIds, true)
            ? array_values(array_diff($this->therapistIds, [$id]))
            : [...$this->therapistIds, $id];

        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function clearTherapists(): void
    {
        $this->therapistIds = [];
        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function weekCountLabel(): string
    {
        $count = $this->weekCount;
        $word = match (true) {
            $count === 1 => 'termín',
            $count >= 2 && $count <= 4 => 'termíny',
            default => 'termínů',
        };

        return "{$count} {$word} tento týden";
    }

    /**
     * @param  array{start: string, end: string, timezone: string}  $info
     */
    public function fetchEvents(array $info): array
    {
        return $this->isTemplateMode()
            ? $this->fetchTemplateEvents($info)
            : $this->fetchReservationEvents($info);
    }

    /**
     * @param  array{start: string, end: string, timezone: string}  $info
     */
    protected function fetchReservationEvents(array $info): array
    {
        $reservations = $this->applyFilters(
            Reservation::query()
                ->with(['client', 'service', 'therapist.user', 'room'])
                ->whereBetween('reservation_date', [substr((string) $info['start'], 0, 10), substr((string) $info['end'], 0, 10)])
        )->get();

        $this->weekCount = $reservations->count();

        $events = $reservations->map(function (Reservation $reservation): array {
            $date = $reservation->reservation_date->toDateString();
            $accent = $this->therapistColor($reservation->therapist_id);
            $tint = $this->therapistTint($reservation->therapist_id);
            $isCancelled = $reservation->status === ReservationStatus::Cancelled;

            [$statusBg, $statusText] = match ($reservation->status?->getColor()) {
                'success' => ['#D3F3DF', '#147638'],
                'warning' => ['#FDECCE', '#A96E09'],
                'danger' => ['#FDD9DF', '#B4173A'],
                default => ['#F5F5F5', '#525252'],
            };

            $statusLabel = match ($reservation->status) {
                ReservationStatus::Confirmed => 'Potvrz.',
                ReservationStatus::Pending => 'Čeká',
                ReservationStatus::Cancelled => 'Storno',
                default => $reservation->status?->getLabel(),
            };

            return EventData::make()
                ->id($reservation->getKey())
                ->title(trim(($reservation->client?->name ?? 'Rezervace').' · '.($reservation->service?->name ?? '')))
                ->start($date.'T'.$reservation->start_time)
                ->end($date.'T'.$reservation->end_time)
                ->backgroundColor($tint)
                ->borderColor($accent)
                ->textColor('#171717')
                ->extendedProps([
                    'kind' => 'reservation',
                    'timeLabel' => substr((string) $reservation->start_time, 0, 5).'–'.substr((string) $reservation->end_time, 0, 5),
                    'client' => $reservation->client?->name,
                    'service' => $reservation->service?->name,
                    'room' => $reservation->room?->name,
                    'initials' => $this->therapistInitials($reservation->therapist?->user?->name),
                    'therapistName' => $reservation->therapist?->user?->name,
                    'accent' => $accent,
                    'statusLabel' => $statusLabel,
                    'statusBg' => $statusBg,
                    'statusText' => $statusText,
                    'isCancelled' => $isCancelled,
                    'isTrashed' => $reservation->trashed(),
                    'isSelected' => in_array((string) $reservation->getKey(), $this->selectedIds, true),
                ])
                ->toArray();
        })->all();

        return [...$events, ...$this->fetchOneTimeBlockingEvents($info)];
    }

    /**
     * One-time room blockings overlaid on the reservations grid so manually
     * added blocks are visible. Honours the room filter; therapist filters and
     * multi-day therapist calendar blocks do not apply here.
     *
     * @param  array{start: string, end: string, timezone: string}  $info
     * @return array<int, mixed>
     */
    protected function fetchOneTimeBlockingEvents(array $info): array
    {
        $roomIds = $this->room ? [] : ($this->filterData['roomIds'] ?? []);
        $start = substr((string) $info['start'], 0, 10);
        $end = substr((string) $info['end'], 0, 10);

        [$accent, $tint] = self::BLOCKING_COLORS;

        return RoomBlocking::query()
            ->with('room')
            ->where('is_recurring', false)
            ->whereNotNull('start_at')
            ->where('start_at', '<', $end.' 00:00:00')
            ->where('end_at', '>', $start.' 00:00:00')
            ->when($this->room, fn (Builder $query) => $query->where('room_id', $this->room->getKey()))
            ->when($roomIds, fn (Builder $query) => $query->whereIn('room_id', $roomIds))
            ->get()
            ->map(fn (RoomBlocking $blocking): array => EventData::make()
                ->id('blocking:'.$blocking->getKey())
                ->title($blocking->reason ?: 'Blokace')
                ->start($blocking->start_at?->format('Y-m-d\TH:i:s'))
                ->end($blocking->end_at?->format('Y-m-d\TH:i:s'))
                ->backgroundColor($tint)
                ->borderColor($accent)
                ->textColor('#3730A3')
                ->extendedProps([
                    'kind' => 'blocking',
                    'timeLabel' => $blocking->start_at?->format('H:i').'–'.$blocking->end_at?->format('H:i'),
                    'title' => $blocking->reason ?: 'Blokace',
                    'room' => $blocking->room?->name,
                ])
                ->toArray())
            ->all();
    }

    /**
     * Map recurring working hours + recurring blockings onto the displayed week.
     * The week shown is a generic representation; the "Typ týdne" selector — not
     * the real calendar parity — decides which odd/even rows appear.
     *
     * @param  array{start: string, end: string, timezone: string}  $info
     */
    protected function fetchTemplateEvents(array $info): array
    {
        $start = Carbon::parse(substr((string) $info['start'], 0, 10));
        $end = Carbon::parse(substr((string) $info['end'], 0, 10));

        /** @var array<string, Carbon> $dates weekday value => concrete date in the displayed week */
        $dates = [];
        for ($day = $start->copy(); $day->lt($end); $day->addDay()) {
            $dates[DayOfWeek::fromCarbon($day)->value] = $day->copy();
        }

        $allowedWeekTypes = $this->allowedTemplateWeekTypes();

        $events = [];

        $schedules = TherapistWeeklySchedule::query()
            ->with(['therapist.user', 'room'])
            ->when($this->therapistIds, fn (Builder $query) => $query->whereIn('therapist_id', $this->therapistIds))
            ->when($this->templateRoomId, fn (Builder $query) => $query->where('room_id', $this->templateRoomId))
            ->when($this->room, fn (Builder $query) => $query->where('room_id', $this->room->getKey()))
            ->when($allowedWeekTypes, fn (Builder $query) => $query->whereIn('week_type', $allowedWeekTypes))
            ->get();

        foreach ($schedules as $schedule) {
            $date = $dates[$schedule->day_of_week?->value] ?? null;
            if ($date === null) {
                continue;
            }

            $accent = $this->therapistColor($schedule->therapist_id);

            $events[] = EventData::make()
                ->id('schedule:'.$schedule->getKey())
                ->title((string) ($schedule->therapist?->user?->name ?? 'Pracovní doba'))
                ->start($date->toDateString().'T'.$schedule->start_time)
                ->end($date->toDateString().'T'.$schedule->end_time)
                ->backgroundColor($this->therapistTint($schedule->therapist_id))
                ->borderColor($accent)
                ->textColor('#171717')
                ->extendedProps([
                    'kind' => 'schedule',
                    'timeLabel' => $this->timeLabel($schedule->start_time, $schedule->end_time),
                    'therapistName' => $schedule->therapist?->user?->name,
                    'initials' => $this->therapistInitials($schedule->therapist?->user?->name),
                    'room' => $schedule->room?->name,
                    'accent' => $accent,
                    'isSelected' => in_array('schedule:'.$schedule->getKey(), $this->selectedIds, true),
                ])
                ->toArray();
        }

        $blockings = RoomBlocking::query()
            ->with('room')
            ->where('is_recurring', true)
            ->when($this->templateRoomId, fn (Builder $query) => $query->where('room_id', $this->templateRoomId))
            ->when($this->room, fn (Builder $query) => $query->where('room_id', $this->room->getKey()))
            ->when($allowedWeekTypes, fn (Builder $query) => $query->whereIn('week_type', $allowedWeekTypes))
            ->get();

        [$blockAccent, $blockTint] = self::BLOCKING_COLORS;

        foreach ($blockings as $blocking) {
            $date = $dates[$blocking->day_of_week?->value] ?? null;
            if ($date === null) {
                continue;
            }

            $events[] = EventData::make()
                ->id('blocking:'.$blocking->getKey())
                ->title($blocking->reason ?: 'Blokace')
                ->start($date->toDateString().'T'.$blocking->start_time)
                ->end($date->toDateString().'T'.$blocking->end_time)
                ->backgroundColor($blockTint)
                ->borderColor($blockAccent)
                ->textColor('#3730A3')
                ->extendedProps([
                    'kind' => 'blocking',
                    'timeLabel' => $this->timeLabel($blocking->start_time, $blocking->end_time),
                    'title' => $blocking->reason ?: 'Blokace',
                    'room' => $blocking->room?->name,
                    'isSelected' => in_array('blocking:'.$blocking->getKey(), $this->selectedIds, true),
                ])
                ->toArray();
        }

        return $events;
    }

    /**
     * Allowed week_type values for the current template selector, or null for "all".
     *
     * @return array<int, string>|null
     */
    protected function allowedTemplateWeekTypes(): ?array
    {
        return match ($this->templateWeekType) {
            'odd' => [WeekType::All->value, WeekType::Odd->value],
            'even' => [WeekType::All->value, WeekType::Even->value],
            default => null,
        };
    }

    protected function timeLabel(?string $start, ?string $end): string
    {
        return substr((string) $start, 0, 5).'–'.substr((string) $end, 0, 5);
    }

    // ---- Reservations sidebar: mini calendar + day summary --------------------

    public function selectedDate(): Carbon
    {
        return $this->calendarDate ? Carbon::parse($this->calendarDate) : Carbon::today();
    }

    protected function sidebarMonthStart(): Carbon
    {
        return ($this->sidebarMonth ? Carbon::parse($this->sidebarMonth.'-01') : $this->selectedDate())->startOfMonth();
    }

    public function sidebarMonthLabel(): string
    {
        return ucfirst($this->sidebarMonthStart()->locale('cs')->isoFormat('MMMM YYYY'));
    }

    /**
     * Mini-calendar grid: an array of weeks, each a list of day descriptors.
     *
     * @return array<int, array<int, array{date: string, day: int, inMonth: bool, isToday: bool, isSelected: bool}>>
     */
    public function sidebarMonthGrid(): array
    {
        $month = $this->sidebarMonthStart();
        $cursor = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $today = Carbon::today()->toDateString();
        $selected = $this->selectedDate()->toDateString();

        $weeks = [];
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = [
                    'date' => $cursor->toDateString(),
                    'day' => $cursor->day,
                    'inMonth' => $cursor->month === $month->month,
                    'isToday' => $cursor->toDateString() === $today,
                    'isSelected' => $cursor->toDateString() === $selected,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    public function sidebarPrevMonth(): void
    {
        $this->sidebarMonth = $this->sidebarMonthStart()->subMonth()->format('Y-m');
    }

    public function sidebarNextMonth(): void
    {
        $this->sidebarMonth = $this->sidebarMonthStart()->addMonth()->format('Y-m');
    }

    public function goToDate(string $date): void
    {
        $this->calendarDate = $date;
        $this->sidebarMonth = Carbon::parse($date)->format('Y-m');
        $this->dispatch('calendar-goto', date: $date);
    }

    public function toggleSidebar(): void
    {
        $this->sidebarCollapsed = ! $this->sidebarCollapsed;
    }

    /**
     * Stats for the selected day, following the active therapist chip filter.
     *
     * @return array{label: string, count: int, hours: string, free: string, utilization: int}
     */
    public function daySummary(): array
    {
        $date = $this->selectedDate();

        $reservations = Reservation::query()
            ->whereDate('reservation_date', $date->toDateString())
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->when($this->therapistIds, fn (Builder $query) => $query->whereIn('therapist_id', $this->therapistIds))
            ->when($this->room, fn (Builder $query) => $query->where('room_id', $this->room->getKey()))
            ->get(['start_time', 'end_time']);

        $booked = (int) $reservations->sum(fn (Reservation $reservation): int => $this->minutesBetween($reservation->start_time, $reservation->end_time));
        $available = app(CalendarAvailability::class)->availableMinutes($date, $this->therapistIds, $this->room?->getKey());

        return [
            'label' => ucfirst($date->locale('cs')->isoFormat('dd D. MMMM')),
            'count' => $reservations->count(),
            'hours' => number_format($booked / 60, 1, ',', ' '),
            'free' => $this->formatDuration(max(0, $available - $booked)),
            'utilization' => $available > 0 ? (int) round($booked / $available * 100) : 0,
        ];
    }

    protected function minutesBetween(?string $start, ?string $end): int
    {
        if (blank($start) || blank($end)) {
            return 0;
        }

        $toMinutes = function (string $time): int {
            [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

            return ((int) $hours) * 60 + (int) $minutes;
        };

        return max(0, $toMinutes($end) - $toMinutes($start));
    }

    protected function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $remainder > 0 ? "{$hours}h {$remainder}m" : "{$hours}h";
    }

    // ---- Event rendering ------------------------------------------------------

    public function eventContent(): string
    {
        return <<<'JS'
            function (arg) {
                var p = arg.event.extendedProps || {};
                var esc = function (value) {
                    return (value == null ? '' : String(value)).replace(/[&<>"]/g, function (char) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char];
                    });
                };

                if (p.kind === 'blocking') {
                    var broom = p.room ? '<span class="ff-event-room">' + esc(p.room) + '</span>' : '';
                    return { html:
                        '<div class="ff-event ff-event-block">' +
                            '<div class="ff-event-head"><span class="ff-event-time">' + esc(p.timeLabel) + '</span></div>' +
                            '<div class="ff-event-title">' + esc(p.title) + '</div>' +
                            (broom ? '<div class="ff-event-foot"><span></span>' + broom + '</div>' : '') +
                        '</div>'
                    };
                }

                if (p.kind === 'schedule') {
                    var sroom = p.room ? '<span class="ff-event-room">' + esc(p.room) + '</span>' : '<span></span>';
                    return { html:
                        '<div class="ff-event">' +
                            '<div class="ff-event-head"><span class="ff-event-time" style="color:' + p.accent + '">' + esc(p.timeLabel) + '</span></div>' +
                            '<div class="ff-event-title">' + esc(p.therapistName) + '</div>' +
                            '<div class="ff-event-foot">' +
                                '<span class="ff-event-avatar" title="' + esc(p.therapistName) + '" style="background:' + p.accent + '">' + esc(p.initials) + '</span>' +
                                sroom +
                            '</div>' +
                        '</div>'
                    };
                }

                var tag = p.statusLabel
                    ? '<span class="ff-event-tag" style="background:' + p.statusBg + ';color:' + p.statusText + '">' + esc(p.statusLabel) + '</span>'
                    : '';
                var sub = p.service ? '<div class="ff-event-sub">' + esc(p.service) + '</div>' : '';
                var room = p.room ? '<span class="ff-event-room">' + esc(p.room) + '</span>' : '<span></span>';
                var html =
                    '<div class="ff-event">' +
                        '<div class="ff-event-head">' +
                            '<span class="ff-event-time" style="color:' + p.accent + '">' + esc(p.timeLabel) + '</span>' +
                            tag +
                        '</div>' +
                        '<div class="ff-event-title">' + esc(p.client) + '</div>' +
                        sub +
                        '<div class="ff-event-foot">' +
                            '<span class="ff-event-avatar" title="' + esc(p.therapistName) + '" style="background:' + p.accent + '">' + esc(p.initials) + '</span>' +
                            room +
                        '</div>' +
                    '</div>';

                return { html: html };
            }
        JS;
    }

    public function eventClassNames(): string
    {
        return <<<'JS'
            function (arg) {
                var p = arg.event.extendedProps || {};
                var classes = [];
                if (p.kind === 'blocking') { classes.push('ff-blocking'); }
                if (p.isCancelled) { classes.push('ff-cancelled'); }
                if (p.isTrashed) { classes.push('ff-trashed'); }
                if (p.isSelected) { classes.push('ff-selected'); }
                return classes;
            }
        JS;
    }

    /**
     * Resolve a clicked event to its record with soft-deleted reservations included,
     * so the edit modal opens for trashed reservations (visible under the „Koš"
     * trashed filter) instead of throwing ModelNotFoundException → 404 in the modal.
     */
    protected function getEloquentQuery(): Builder
    {
        return Reservation::query()->withTrashed();
    }

    public function getFormSchema(): array
    {
        return [
            // Filament action modals have no header-bar action slot, so this
            // renders a right-aligned "open detail" link at the top of the modal
            // content. Hidden on create because there is no record yet.
            SchemaActions::make([
                Action::make('openDetail')
                    ->label('Otevřít detail')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (?Reservation $record): ?string => $record?->exists
                        ? ReservationResource::getUrl('view', ['record' => $record])
                        : null),
            ])
                ->alignment(Alignment::End)
                ->visible(fn (?Reservation $record): bool => (bool) $record?->exists)
                ->columnSpanFull(),
            ...ReservationForm::components(),
            Toggle::make('notify_client')
                ->label('Upozornit zákazníka?')
                ->helperText('Po uložení odešle zákazníkovi e-mail o vytvoření či změně rezervace.')
                ->default(true)
                ->columnSpanFull(),
        ];
    }

    /**
     * Route event clicks: template blocks open their edit modal; reservations
     * open the edit modal (or toggle selection in selection mode).
     *
     * @param  array<string, mixed>  $event
     */
    public function onEventClick(array $event): void
    {
        if ($this->isTemplateMode()) {
            $eventId = (string) ($event['id'] ?? '');

            if ($eventId === '') {
                return;
            }

            if ($this->selectionMode) {
                $this->toggleSelection($eventId);

                return;
            }

            [$kind, $id] = array_pad(explode(':', $eventId), 2, null);

            if ($id === null) {
                return;
            }

            $this->editingTemplateId = $id;

            if ($kind === 'schedule') {
                $this->mountAction('editSchedule');
            } elseif ($kind === 'blocking') {
                $this->mountAction('editBlocking');
            }

            return;
        }

        $id = (string) ($event['id'] ?? '');
        $isBlocking = str_starts_with($id, 'blocking:');

        if ($this->selectionMode) {
            if (! $isBlocking) {
                $this->toggleSelection($id);
            }

            return;
        }

        if ($isBlocking) {
            $this->editingTemplateId = substr($id, strlen('blocking:'));
            $this->mountAction('editOneTimeBlocking');

            return;
        }

        if ($this->getModel()) {
            $this->record = $this->resolveRecord($event['id']);
        }

        $this->mountAction('edit', [
            'type' => 'click',
            'event' => $event,
        ]);
    }

    protected function headerActions(): array
    {
        if ($this->isTemplateMode()) {
            return [];
        }

        return [
            FullCalendarCreateAction::make()
                ->label('Nová rezervace')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('Nová rezervace')
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->after(function (FullCalendarWidget $livewire, ?Model $record, array $data): void {
                    if ($record instanceof Reservation && ($data['notify_client'] ?? false)) {
                        $record->client?->notify(new ReservationNotification($record, 'created'));
                    }

                    $livewire->refreshRecords();
                }),
        ];
    }

    protected function modalActions(): array
    {
        return [
            FullCalendarEditAction::make()
                ->label('Upravit')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalHeading('Upravit rezervaci')
                // Keep the heading and footer actions in view while the (tall) form
                // body scrolls between them.
                ->stickyModalHeader()
                ->stickyModalFooter()
                // Normal dialog footer: cancel + delete on the left, save on the
                // right. The order styles arrange the buttons; the save button's
                // auto start-margin pins it to the right edge (see admin theme.css).
                ->modalFooterActionsAlignment(Alignment::Between)
                ->modalSubmitAction(fn (Action $submit) => $submit
                    ->icon('lucide-save')
                    ->extraAttributes(['style' => 'order:2;margin-inline-start:auto;']))
                ->before(function (Reservation $record): void {
                    // Snapshot the original values before the edit is saved so the
                    // "reservation changed" e-mail can show the puvodni_* tokens.
                    $this->reservationChangeSnapshot = ReservationChangeSnapshot::capture($record);
                })
                ->extraModalFooterActions([
                    FullCalendarDeleteAction::make()
                        ->label('Smazat')
                        ->extraAttributes(['style' => 'order:1'])
                        ->modalHeading('Smazat rezervaci?')
                        ->modalDescription(fn (Reservation $record): HtmlString => ReservationSummary::description($record))
                        ->modalSubmitActionLabel('Smazat')
                        ->schema([
                            Toggle::make('notify_client')
                                ->label('Informovat klienta e-mailem')
                                ->default(false),
                        ])
                        ->before(function (Reservation $record, array $data): void {
                            if ($data['notify_client'] ?? false) {
                                $record->client?->notify(new ReservationNotification($record, 'cancelled'));
                            }
                        }),
                    // Shown only when the opened reservation is soft-deleted (Filament's
                    // RestoreAction is visible only for trashed records). Bound to the
                    // widget's record like Saade's DeleteAction, and closes the edit modal
                    // + refreshes the calendar afterwards.
                    RestoreAction::make()
                        ->label('Obnovit')
                        ->extraAttributes(['style' => 'order:1'])
                        ->modalHeading('Obnovit rezervaci?')
                        ->modalDescription(fn (Reservation $record): HtmlString => ReservationSummary::description($record))
                        ->modalSubmitActionLabel('Obnovit')
                        ->model(fn (FullCalendarWidget $livewire): ?string => $livewire->getModel())
                        ->record(fn (FullCalendarWidget $livewire): ?Model => $livewire->getRecord())
                        ->cancelParentActions()
                        ->after(function (FullCalendarWidget $livewire): void {
                            $livewire->record = null;
                            $livewire->refreshRecords();
                        }),
                ])
                ->after(function (FullCalendarWidget $livewire, Model $record, array $data): void {
                    if ($record instanceof Reservation && ($data['notify_client'] ?? false)) {
                        $record->client?->notify(new ReservationTemplateNotification($record, EmailTemplateKey::ReservationChanged, $this->reservationChangeSnapshot));
                    }

                    $livewire->refreshRecords();
                }),
        ];
    }
}
