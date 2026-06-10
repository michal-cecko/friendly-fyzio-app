<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Resources\Reservations\Tables\ReservationsTable;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Notifications\ReservationNotification;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Saade\FilamentFullCalendar\Actions\CreateAction as FullCalendarCreateAction;
use Saade\FilamentFullCalendar\Actions\DeleteAction as FullCalendarDeleteAction;
use Saade\FilamentFullCalendar\Actions\EditAction as FullCalendarEditAction;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ReservationCalendar extends FullCalendarWidget implements HasTable
{
    use InteractsWithTable;

    /** @var array<int, string> */
    #[Url(as: 'therapists')]
    public array $therapistIds = [];

    #[Url(as: 'q')]
    public string $search = '';

    /** Whether the collapsible filter selects are expanded. */
    public bool $showFilters = false;

    /** Whether the reservations list table is shown in place of the calendar. */
    #[Url(as: 'list')]
    public bool $listMode = false;

    /** Active FullCalendar view (timeGridWeek / timeGridDay), persisted to the URL. */
    #[Url(as: 'view')]
    public ?string $calendarView = null;

    /** Date shown in the calendar (Y-m-d), persisted to the URL. */
    #[Url(as: 'date')]
    public ?string $calendarDate = null;

    /** Whether clicking a calendar card selects it (for bulk actions) instead of opening the edit modal. */
    public bool $selectionMode = false;

    /**
     * Reservation IDs currently selected for bulk actions. Persists across week/day navigation.
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

    public function getModel(): ?string
    {
        return Reservation::class;
    }

    public function config(): array
    {
        return [
            'headerToolbar' => false,
            'initialView' => $this->calendarView ?: 'timeGridWeek',
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
            'nowIndicator' => true,
            'expandRows' => true,
            'height' => 'auto',
            'locale' => 'cs',
        ];
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

    public function mount(): void
    {
        // Livewire merges any URL query params over the property default above,
        // so $filterData already carries every key (URL values + defaults).
        $this->filtersForm->fill($this->filterData ?? []);
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

        foreach (['clientIds', 'roomIds', 'statusIds', 'serviceIds'] as $key) {
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

    // TODO: The "Odeslat e-mail" bulk action is deferred until the planned Mason
    // vendor package for pages & email templates lands — client-facing emails will
    // be built there. Re-enable this method and its button in the selection bar
    // (resources/views/filament/widgets/reservation-calendar.blade.php) afterwards.
    // public function sendEmailAction(): Action
    // {
    //     return Action::make('sendEmail')
    //         ->label('Odeslat e-mail')
    //         ->icon(Heroicon::OutlinedEnvelope)
    //         ->color('gray')
    //         ->disabled(fn (): bool => $this->selectedIds === [])
    //         ->requiresConfirmation()
    //         ->modalHeading('Odeslat e-mail klientům')
    //         ->modalDescription(fn (): string => $this->selectedCountLabel())
    //         ->action(function (): void {
    //             $this->selectedReservations()->each(
    //                 fn (Reservation $reservation) => $reservation->client?->notify(new ReservationNotification($reservation, 'reminder'))
    //             );
    //
    //             Notification::make()->success()->title('E-maily byly odeslány')->send();
    //
    //             $this->clearSelection();
    //         });
    // }

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
                        $reservation->client?->notify(new ReservationNotification($reservation, 'cancelled'));
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
                    ->multiple()
                    ->options(fn (): array => $this->rooms()
                        ->mapWithKeys(fn (Room $room): array => [
                            $room->getKey() => $room->building
                                ? "{$room->name} · {$room->building->name}"
                                : $room->name,
                        ])
                        ->all())
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

    public function table(Table $table): Table
    {
        // The list view shares the calendar's filter bar, so drive the table
        // from the same state and drop the table's own (now redundant) filters.
        return ReservationsTable::configure(
            $table->query(fn (): Builder => $this->applyFilters(
                Reservation::query()->with(['client', 'service', 'therapist.user', 'room'])
            ))
        )->filters([]);
    }

    /**
     * Apply the shared filter-bar state (therapists, clients, rooms, services,
     * status, search, trashed) to a reservations query. Used by both the
     * calendar grid and the inline list table.
     */
    protected function applyFilters(Builder $query): Builder
    {
        $clientIds = $this->filterData['clientIds'] ?? [];
        $roomIds = $this->filterData['roomIds'] ?? [];
        $serviceIds = $this->filterData['serviceIds'] ?? [];
        $statusIds = $this->filterData['statusIds'] ?? [];
        $trashed = $this->filterData['trashed'] ?? 'without';

        return $query
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

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->dispatch('filament-fullcalendar--refresh');
        }
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
        $reservations = $this->applyFilters(
            Reservation::query()
                ->with(['client', 'service', 'therapist.user', 'room'])
                ->whereBetween('reservation_date', [substr((string) $info['start'], 0, 10), substr((string) $info['end'], 0, 10)])
        )->get();

        $this->weekCount = $reservations->count();

        return $reservations->map(function (Reservation $reservation): array {
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
    }

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
                if (p.isCancelled) { classes.push('ff-cancelled'); }
                if (p.isTrashed) { classes.push('ff-trashed'); }
                if (p.isSelected) { classes.push('ff-selected'); }
                return classes;
            }
        JS;
    }

    public function getFormSchema(): array
    {
        return ReservationForm::components();
    }

    /**
     * Open the edit modal directly when an event is clicked, skipping the
     * read-only detail modal.
     *
     * @param  array<string, mixed>  $event
     */
    public function onEventClick(array $event): void
    {
        if ($this->selectionMode) {
            $this->toggleSelection((string) $event['id']);

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
        return [
            FullCalendarCreateAction::make()
                ->label('Nová rezervace')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('Nová rezervace')
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
                ->extraModalFooterActions([
                    FullCalendarDeleteAction::make()
                        ->label('Smazat')
                        ->modalHeading('Smazat rezervaci')
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
                ])
                ->after(function (FullCalendarWidget $livewire, Model $record, array $data): void {
                    if ($record instanceof Reservation && ($data['notify_client'] ?? false)) {
                        $record->client?->notify(new ReservationNotification($record, 'updated'));
                    }

                    $livewire->refreshRecords();
                }),
        ];
    }
}
