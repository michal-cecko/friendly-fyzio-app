<?php

namespace App\Filament\Widgets;

use App\Enums\Capability;
use App\Enums\DayOfWeek;
use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\ReservationDayWaitlist\ReservationDayWaitlistResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CancelReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RestoreReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\Actions\ScheduleChangeNotificationPrompt;
use App\Filament\Support\Schemas\BlockingForm;
use App\Filament\Support\Schemas\WorkingHoursForm;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Models\TherapistWorkBlockSeries;
use App\Models\User;
use App\Notifications\ReservationNotification;
use App\Notifications\ReservationTemplateNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Avatar;
use App\Support\CalendarAvailability;
use App\Support\Emails\SentEmailReceipt;
use App\Support\Reservations\NotifyReservationChange;
use App\Support\Reservations\ReactivateReservation;
use App\Support\Reservations\ReservationChangeSnapshot;
use App\Support\Reservations\SlotTakenException;
use App\Support\Settings;
use App\Support\WorkBlocks\WorkBlockGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Saade\FilamentFullCalendar\Actions\CreateAction as FullCalendarCreateAction;
use Saade\FilamentFullCalendar\Actions\EditAction as FullCalendarEditAction;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ReservationCalendar extends FullCalendarWidget
{
    /** Indigo accent/tint used for blocking events (matches the "Blokace" legend). */
    private const BLOCKING_COLORS = ['#6366F1', '#EEF2FF'];

    /** Cyan accent/tint used for course lesson events (matches the "Kurzy" legend). */
    private const COURSE_COLORS = ['#0891B2', '#ECFEFF'];

    /** Fuchsia accent/tint used for one-off event entries (matches the "Akce" legend). */
    private const ONE_OFF_COLORS = ['#C026D3', '#FDF4FF'];

    /** Neutral accent/tint for the break strip trailing a reservation card. */
    private const BREAK_COLORS = ['#A3A3A3', '#F5F5F5'];

    /**
     * When set, the calendar is scoped to a single room: every query is filtered
     * by this room, the room filter UI is hidden, and the add/edit blocking and
     * working-hours modals lock to it. Null on the global calendar page.
     */
    public ?Room $room = null;

    /** Calendar mode: 'reservations' (real bookings) or 'template' (working hours). */
    #[Url(as: 'mode')]
    public string $mode = 'reservations';

    /** @var array<int, string> */
    #[Url(as: 'therapists')]
    public array $therapistIds = [];

    #[Url(as: 'q')]
    public string $search = '';

    /** Whether the collapsible filter selects are expanded. */
    public bool $showFilters = false;

    /** Reservations mode: show reservation events ("Terapie" legend toggle). */
    #[Url(as: 'terapie')]
    public bool $showReservations = true;

    /** Reservations mode: overlay scheduled course lessons (read-only). */
    #[Url(as: 'kurzy')]
    public bool $showCourses = true;

    /** Reservations mode: overlay scheduled one-off events (read-only). */
    #[Url(as: 'akce')]
    public bool $showLessons = true;

    /** Reservations mode: show the day-waitlist summary strip above the grid. */
    #[Url(as: 'poradnik')]
    public bool $showWaitlist = true;

    /** Date shown in the calendar (Y-m-d), persisted to the URL. */
    #[Url(as: 'date')]
    public ?string $calendarDate = null;

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

    /** @var Collection<int, StaffProfile>|null */
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
            'nowIndicator' => true,
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
     * The staff shown as calendar columns/chips: current team members who treat
     * or teach. A profile qualifies only when it is published (the same flag the
     * public team page uses to mean "current staff") and its user is active and
     * holds the Therapist or Lecturer capability. This deliberately hides staff
     * that exist purely for historical records — deactivated ex-therapists and
     * the lecturer-only accounts created by the historical courses import — as
     * well as admin/assistant profiles that neither treat nor teach.
     *
     * @return Collection<int, StaffProfile>
     */
    public function therapists(): Collection
    {
        // A pure therapist (therapist, not also an admin) only sees their own
        // calendar; admins keep the all-therapists view.
        $user = auth()->user();
        $pureTherapist = $user instanceof User && $user->isTherapist() && ! $user->isAdmin();

        return $this->therapistCache ??= StaffProfile::query()->with('user')
            ->published()
            ->whereHas('user', fn (Builder $query): Builder => $query
                ->whereNull('deactivated_at')
                ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', [
                    Capability::Therapist->roleName(),
                    Capability::Lecturer->roleName(),
                ])))
            ->when($pureTherapist, fn (Builder $query) => $query->where('user_id', $user->getKey()))
            ->get();
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
                $room->getKey() => $room->picker_label,
            ])
            ->all();
    }

    /**
     * The room ids every overlay query must be restricted to, resolved from
     * whichever room control the current context uses: a room-scoped calendar
     * pins its own room, template mode uses the toolbar select, and reservations
     * mode uses the filter form. An empty array means "all rooms".
     *
     * @return array<int, string>
     */
    public function scopedRoomIds(): array
    {
        if ($this->room !== null) {
            return [(string) $this->room->getKey()];
        }

        if ($this->isTemplateMode()) {
            return array_values(array_filter([$this->templateRoomId]));
        }

        return array_values($this->filterData['roomIds'] ?? []);
    }

    public function mount(?Room $room = null): void
    {
        // Set only when provided (room-scoped View page); the global calendar
        // page mounts the widget without a room.
        if ($room !== null) {
            $this->room = $room;
        }

        // A pure therapist's calendar defaults to their own schedule (unless a URL
        // filter says otherwise); admins keep the all-therapists view.
        $user = auth()->user();
        if ($user instanceof User && $user->isTherapist() && ! $user->isAdmin() && $this->therapistIds === []) {
            $ownProfileId = $user->staffProfile?->getKey();
            if ($ownProfileId !== null) {
                $this->therapistIds = [$ownProfileId];
            }
        }

        // Livewire merges any URL query params over the property default above,
        // so $filterData already carries every key (URL values + defaults).
        $this->filtersForm->fill($this->filterData ?? []);

        $this->sidebarMonth ??= $this->selectedDate()->format('Y-m');
    }

    public function updatedCalendarDate(): void
    {
        if ($this->calendarDate === null) {
            return;
        }

        // The main calendar's top arrows update only $calendarDate (via JS), so
        // keep the sidebar mini-calendar's month in sync when the view crosses a
        // month boundary — mirroring what goToDate() does for day clicks.
        $this->sidebarMonth = Carbon::parse($this->calendarDate)->format('Y-m');
    }

    public function updated(string $property): void
    {
        if ($property === 'mode') {
            // Selected IDs are mode-specific (reservation keys in reservations
            // mode, schedule:/blocking: keys in template mode), so don't carry
            // a stale selection across the toggle.
            $this->selectedIds = [];
        }

        if (in_array($property, ['search', 'mode', 'templateRoomId', 'showReservations', 'showCourses', 'showLessons'], true)) {
            $this->dispatch('filament-fullcalendar--refresh');
        }
    }

    public function resetFilters(): void
    {
        $this->therapistIds = [];
        $this->search = '';
        $this->showReservations = true;
        $this->showCourses = true;
        $this->showLessons = true;
        $this->showWaitlist = true;
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

    /**
     * Whether the current selection contains anything ReactivateReservation could
     * act on, so the restore action stays hidden for purely active selections.
     */
    protected function hasRestorableSelection(): bool
    {
        if ($this->selectedIds === []) {
            return false;
        }

        return Reservation::query()
            ->withTrashed()
            ->whereIn('id', $this->selectedIds)
            ->where(fn (Builder $query) => $query
                ->whereNotNull('deleted_at')
                ->orWhere('status', ReservationStatus::Cancelled))
            ->exists();
    }

    protected function selectedCountLabel(): string
    {
        return 'Počet vybraných rezervací: '.count($this->selectedIds);
    }

    /**
     * Mirrors CancelReservationBulkAction (the reservations list) so the calendar
     * offers the exact same modal: required reason, "Úplně vymazat ze systému?"
     * opt-in (trash → pruned for good after 30 days) and the client notification
     * toggle. Replaces the old separate cancel + delete pair.
     */
    public function cancelSelectedAction(): Action
    {
        return Action::make('cancelSelected')
            ->label('Zrušit rezervace')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->disabled(fn (): bool => $this->selectedIds === [])
            ->modalHeading('Zrušit vybrané rezervace')
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalSubmitActionLabel('Zrušit rezervace')
            ->modalDescription(fn (): string => $this->selectedCountLabel())
            ->schema([
                Textarea::make('cancellation_reason')
                    ->label('Důvod zrušení')
                    ->rows(2)
                    ->required(),
                Toggle::make('force_delete')
                    ->label('Úplně vymazat ze systému?')
                    ->helperText('Zapnuto: klienti dostanou běžné oznámení o zrušení a rezervace se přesunou do koše — po 30 dnech se ze systému nenávratně vymažou (platby a faktury zůstávají). Vypnuto: zůstanou v evidenci jako stornované.')
                    ->default(false),
                Toggle::make('notify_client')
                    ->label('Informovat klienty e-mailem')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $this->selectedReservations()->each(function (Reservation $reservation) use ($data): void {
                    if ($reservation->trashed()) {
                        return;
                    }

                    $reservation->update([
                        'status' => ReservationStatus::Cancelled,
                        'cancellation_reason' => $data['cancellation_reason'],
                    ]);

                    if ($data['notify_client'] ?? false) {
                        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationCancelled));

                        SentEmailReceipt::forCurrentUser('Zrušení rezervace');
                    }

                    if ($data['force_delete'] ?? false) {
                        $reservation->delete();
                    }
                });

                Notification::make()->success()->title('Vybrané rezervace byly zrušeny.')->send();

                $this->clearSelection();
            });
    }

    /**
     * Mirrors RestoreReservationBulkAction (the reservations list): reactivates
     * every selected trashed/cancelled reservation via ReactivateReservation,
     * skipping already-active ones and meanwhile-occupied slots.
     */
    public function restoreSelectedAction(): Action
    {
        return Action::make('restoreSelected')
            ->label('Obnovit')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => $this->hasRestorableSelection())
            ->modalHeading('Obnovit vybrané rezervace')
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalSubmitActionLabel('Obnovit rezervace')
            ->modalDescription(fn (): string => $this->selectedCountLabel())
            ->schema([
                Toggle::make('notify_client')
                    ->label('Informovat klienty e-mailem')
                    ->helperText('Klienti dostanou běžné potvrzení rezervace; termíny už v potvrzovacím okně se rovnou potvrdí s e-mailem o automatickém potvrzení.')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $restored = 0;
                $skipped = 0;

                $this->selectedReservations()->each(function (Reservation $reservation) use ($data, &$restored, &$skipped): void {
                    if (! $reservation->trashed() && $reservation->status !== ReservationStatus::Cancelled) {
                        $skipped++;

                        return;
                    }

                    try {
                        app(ReactivateReservation::class)->handle($reservation, (bool) ($data['notify_client'] ?? false));
                        $restored++;
                    } catch (SlotTakenException) {
                        $skipped++;
                    }
                });

                Notification::make()
                    ->title("Obnovené rezervace: {$restored}.")
                    ->body($skipped > 0 ? "Přeskočeno: {$skipped} (aktivní, nebo už obsazený termín)." : null)
                    ->success()
                    ->send();

                $this->clearSelection();
            });
    }

    /**
     * Bulk-delete the items selected in template mode: dated work blocks
     * (schedule:*) and blockings (blocking:*).
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
                $workBlockIds = [];
                $blockingIds = [];

                foreach ($this->selectedIds as $selectedId) {
                    [$kind, $id] = array_pad(explode(':', $selectedId), 2, null);

                    if ($id === null) {
                        continue;
                    }

                    if ($kind === 'schedule') {
                        $workBlockIds[] = $id;
                    } elseif ($kind === 'blocking') {
                        $blockingIds[] = $id;
                    }
                }

                if ($workBlockIds !== []) {
                    TherapistWorkBlock::whereIn('id', $workBlockIds)->delete();
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
     * Dated working block(s), created from the template toolbar. A repeat
     * pattern (weekly / odd / even) materializes one row per date via
     * WorkBlockGenerator; without repeat a single block is created.
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

                $repeat = $data['repeat'] ?? 'none';

                if ($repeat === 'none') {
                    TherapistWorkBlock::create([
                        'therapist_id' => $data['therapist_id'],
                        'room_id' => $data['room_id'],
                        'work_date' => $data['work_date'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                    ]);

                    Notification::make()->success()->title('Pracovní doba byla přidána')->send();
                } else {
                    $startsOn = Carbon::parse($data['work_date']);

                    $series = TherapistWorkBlockSeries::create([
                        'therapist_id' => $data['therapist_id'],
                        'room_id' => $data['room_id'],
                        'day_of_week' => DayOfWeek::fromCarbon($startsOn)->value,
                        'week_type' => $repeat === 'weekly' ? 'all' : $repeat,
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'starts_on' => $startsOn->toDateString(),
                        'ends_on' => $data['repeat_until'] ?? null,
                        'generated_until' => $startsOn->copy()->subDay()->toDateString(),
                    ]);

                    $result = app(WorkBlockGenerator::class)->materialize($series, WorkBlockGenerator::horizon());

                    Notification::make()
                        ->success()
                        ->title("Pracovní doba byla přidána ({$result['created']} termínů)")
                        ->body($result['skipped'] > 0 ? "Přeskočeno kvůli překryvu: {$result['skipped']}." : null)
                        ->send();
                }

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    /**
     * Edits one dated work-block occurrence. Blocks generated from a series
     * additionally offer "delete this and all following occurrences".
     */
    public function editScheduleAction(): Action
    {
        return Action::make('editSchedule')
            ->modalHeading('Upravit pracovní dobu')
            ->fillForm(fn (): array => TherapistWorkBlock::find($this->editingTemplateId)?->only([
                'therapist_id', 'room_id', 'work_date', 'start_time', 'end_time',
            ]) ?? [])
            ->schema(fn (): array => WorkingHoursForm::occurrence($this->room?->getKey(), $this->editingTemplateId))
            ->extraModalFooterActions([
                Action::make('deleteSchedule')
                    ->label('Smazat')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation()
                    ->action(function (): void {
                        TherapistWorkBlock::whereKey($this->editingTemplateId)->delete();

                        Notification::make()->success()->title('Pracovní doba byla smazána')->send();

                        $this->dispatch('filament-fullcalendar--refresh');
                    })
                    ->cancelParentActions(),
                Action::make('deleteScheduleFromHere')
                    ->label('Smazat tento a následující')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedTrash)
                    ->visible(fn (): bool => TherapistWorkBlock::find($this->editingTemplateId)?->series_id !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Smazat tento a všechny následující termíny')
                    ->modalDescription('Smaže tento blok a všechny pozdější termíny stejného opakování. Dřívější termíny zůstanou.')
                    ->action(function (): void {
                        $block = TherapistWorkBlock::find($this->editingTemplateId);

                        if ($block === null || $block->series_id === null) {
                            return;
                        }

                        $deleted = TherapistWorkBlock::query()
                            ->where('series_id', $block->series_id)
                            ->whereDate('work_date', '>=', $block->work_date->toDateString())
                            ->delete();

                        $this->truncateSeries($block->series, $block->work_date->copy()->subDay());

                        Notification::make()->success()->title("Smazané termíny: {$deleted}.")->send();

                        $this->dispatch('filament-fullcalendar--refresh');
                    })
                    ->cancelParentActions(),
            ])
            ->action(function (array $data): void {
                if ($this->room) {
                    $data['room_id'] = $this->room->getKey();
                }

                TherapistWorkBlock::whereKey($this->editingTemplateId)->update($data);

                Notification::make()->success()->title('Pracovní doba byla upravena')->send();

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    /**
     * The vacation/absence workflow: bulk-delete a therapist's work blocks in a
     * date range. Deleted occurrences are never regenerated by the series
     * extension (it only appends dates beyond each series' cursor).
     */
    public function deleteWorkBlocksRangeAction(): Action
    {
        return Action::make('deleteWorkBlocksRange')
            ->label('Smazat období')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('danger')
            ->modalHeading('Smazat pracovní dobu v období')
            ->modalDescription('Smaže všechny bloky pracovní doby terapeuta ve zvoleném období (dovolená, nemoc…). Existující rezervace zůstávají — případné zrušení řešte v rezervacích.')
            ->modalSubmitActionLabel('Smazat')
            ->schema([
                Select::make('therapist_id')
                    ->label('Terapeut')
                    ->options(fn (): array => $this->therapists()
                        ->mapWithKeys(fn (StaffProfile $therapist): array => [
                            $therapist->getKey() => $therapist->user?->name ?? '—',
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
                DatePicker::make('from')
                    ->label('Od')
                    ->native(false)
                    ->displayFormat('d. m. Y')
                    ->required(),
                DatePicker::make('until')
                    ->label('Do')
                    ->native(false)
                    ->displayFormat('d. m. Y')
                    ->required()
                    ->afterOrEqual('from'),
            ])
            ->action(function (array $data): void {
                $deleted = TherapistWorkBlock::query()
                    ->where('therapist_id', $data['therapist_id'])
                    ->whereBetween('work_date', [$data['from'], $data['until']])
                    ->when($this->room, fn (Builder $query) => $query->where('room_id', $this->room->getKey()))
                    ->delete();

                $reservations = Reservation::query()
                    ->where('therapist_id', $data['therapist_id'])
                    ->whereBetween('reservation_date', [$data['from'], $data['until']])
                    ->where('status', '!=', ReservationStatus::Cancelled->value)
                    ->count();

                Notification::make()
                    ->success()
                    ->title("Smazané bloky pracovní doby: {$deleted}.")
                    ->body($reservations > 0 ? "Pozor: v období zůstává {$reservations} aktivních rezervací terapeuta." : null)
                    ->send();

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    /**
     * Cap a series so the extension command stops regenerating past the given
     * date; a series trimmed before its own start is deleted entirely.
     */
    protected function truncateSeries(?TherapistWorkBlockSeries $series, Carbon $endsOn): void
    {
        if ($series === null) {
            return;
        }

        if ($endsOn->lessThan($series->starts_on)) {
            $series->delete();

            return;
        }

        $series->update([
            'ends_on' => $endsOn->toDateString(),
            'generated_until' => min($series->generated_until->toDateString(), $endsOn->toDateString()),
        ]);
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
                        ->customers()
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
     *
     * Visits reconstructed from a historical import are always excluded: their
     * times are placeholders assigned at import, so putting them on the calendar
     * would misrepresent how those days actually ran. They remain visible in the
     * client's reservation history.
     */
    protected function applyFilters(Builder $query): Builder
    {
        $clientIds = $this->filterData['clientIds'] ?? [];
        $roomIds = $this->room ? [] : ($this->filterData['roomIds'] ?? []);
        $serviceIds = $this->filterData['serviceIds'] ?? [];
        $statusIds = $this->filterData['statusIds'] ?? [];
        $trashed = $this->filterData['trashed'] ?? 'without';

        return $query
            ->whereNull('imported_at')
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
        return Avatar::initials($name);
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
     * The dates FullCalendar is actually showing, as an inclusive [from, to]
     * pair. The `end` it hands us is exclusive (the first day *after* the view),
     * so it has to lose a day before it can be used in a `whereBetween`.
     *
     * @param  array{start: string, end: string, timezone: string}  $info
     * @return array{0: string, 1: string}
     */
    protected function visibleDateRange(array $info): array
    {
        $start = substr((string) $info['start'], 0, 10);
        $end = Carbon::parse(substr((string) $info['end'], 0, 10))->subDay()->toDateString();

        return [$start, max($start, $end)];
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
        $reservations = $this->showReservations
            ? $this->applyFilters(
                Reservation::query()
                    ->with(['client', 'service', 'therapist.user', 'room'])
                    ->whereBetween('reservation_date', [substr((string) $info['start'], 0, 10), substr((string) $info['end'], 0, 10)])
            )->get()
            : collect();

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
                    'room' => $reservation->room?->display_short_name,
                    'initials' => $this->therapistInitials($reservation->therapist?->user?->name),
                    'therapistName' => $reservation->therapist?->user?->name,
                    'accent' => $accent,
                    'statusLabel' => $statusLabel,
                    'statusBg' => $statusBg,
                    'statusText' => $statusText,
                    'isCancelled' => $isCancelled,
                    'isTrashed' => $reservation->trashed(),
                    'isSelected' => in_array((string) $reservation->getKey(), $this->selectedIds, true),
                    // The break hangs off the bottom of this card rather than
                    // being an event of its own — see breakStrip().
                    ...$this->breakStrip($reservation),
                ])
                ->toArray();
        })->all();

        return [
            ...$events,
            ...$this->fetchOneTimeBlockingEvents($info),
            ...$this->fetchLessonEvents($info),
        ];
    }

    /**
     * Props describing the greyed strip that covers the therapist's break.
     *
     * It is drawn as part of the reservation card, overhanging its bottom edge,
     * rather than as a second event. A separate event would compete for the slot
     * with whatever is booked next and both would be squeezed to half width —
     * the break would no longer line up with the card it belongs to. Hanging it
     * off the card keeps the two exactly the same width, and because later
     * events are painted after it, a booking that really does start inside the
     * break covers it instead of fighting it for room.
     *
     * The height is expressed as a fraction of the card, which is exact: a
     * timegrid card's height *is* its duration, so break ÷ duration of it is the
     * break.
     *
     * @return array<string, mixed>
     */
    protected function breakStrip(Reservation $reservation): array
    {
        $duration = (int) $reservation->startsAt()->diffInMinutes($reservation->endsAt());

        if (! $this->hasBreakStrip($reservation) || $duration <= 0) {
            return ['hasBreak' => false];
        }

        return [
            'hasBreak' => true,
            'breakMinutes' => $reservation->break_minutes,
            'breakRatio' => round($reservation->break_minutes / $duration, 4),
            'breakLabel' => 'Pauza '.$reservation->break_minutes.' min',
            'breakUntil' => $reservation->endsAtIncludingBreak()->format('H:i'),
        ];
    }

    /**
     * Whether a visit leaves a break behind it: a cancelled or binned one frees
     * its break with it.
     */
    protected function hasBreakStrip(Reservation $reservation): bool
    {
        return $reservation->break_minutes > 0
            && $reservation->status !== ReservationStatus::Cancelled
            && ! $reservation->trashed();
    }

    /**
     * Course lessons and one-off events are hidden while a reservation-specific
     * filter (client, service, status, trash-only) is active — those filters ask
     * "which reservations…", so overlaying unrelated lessons would be noise.
     */
    protected function reservationSpecificFilterActive(): bool
    {
        return filled($this->filterData['clientIds'] ?? [])
            || filled($this->filterData['serviceIds'] ?? [])
            || filled($this->filterData['statusIds'] ?? [])
            || ($this->filterData['trashed'] ?? 'without') === 'only';
    }

    /**
     * Therapist chips carry StaffProfile ids, while lessons/events reference
     * users.id via instructor_id — map the selected chips to user ids.
     *
     * @return array<int, string>
     */
    protected function selectedInstructorUserIds(): array
    {
        if ($this->therapistIds === []) {
            return [];
        }

        return $this->therapists()
            ->whereIn('id', $this->therapistIds)
            ->pluck('user_id')
            ->all();
    }

    /**
     * Lessons overlaid read-only on both grids — both the scheduled lessons of a
     * course série and standalone workshops / jednorázové lekce, which are the
     * same record since the merge. The two "Kurzy" / "Akce" toggles select
     * between them rather than between two queries.
     *
     * In template mode they answer "what already competes with this working
     * hour?", so the room scope comes from the toolbar select and the
     * reservation-only filters (client/service/status/trash, which survive in
     * the URL after a mode switch) and the search box do not apply.
     *
     * Honours room scope/filter and therapist chips (mapped to instructor
     * users); clicking navigates to the lesson's admin detail page.
     * Soft-deleted lessons are excluded by the default scope; unpublished ones
     * are shown.
     *
     * @param  array{start: string, end: string, timezone: string}  $info
     * @return array<int, mixed>
     */
    protected function fetchLessonEvents(array $info): array
    {
        if (! $this->showCourses && ! $this->showLessons) {
            return [];
        }

        if (! $this->isTemplateMode() && $this->reservationSpecificFilterActive()) {
            return [];
        }

        $roomIds = $this->scopedRoomIds();
        $instructorUserIds = $this->selectedInstructorUserIds();

        return Lesson::query()
            ->with([
                'series' => fn ($query) => $query->withCount('activeTakers'),
                'series.course',
                'instructor',
                'room',
                'category',
            ])
            ->withCount('activeTakers')
            ->whereBetween('lesson_date', $this->visibleDateRange($info))
            ->unless($this->showCourses, fn (Builder $query) => $query->whereNull('series_id'))
            ->unless($this->showLessons, fn (Builder $query) => $query->whereNotNull('series_id'))
            ->when($roomIds, fn (Builder $query) => $query->whereIn('room_id', $roomIds))
            ->when($this->therapistIds, fn (Builder $query) => $query->whereIn('instructor_id', $instructorUserIds))
            ->when(! $this->isTemplateMode() && filled($this->search), function (Builder $query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereHas('series', function (Builder $series) use ($term): void {
                            $series->whereRaw('LOWER(name) LIKE ?', [$term])
                                ->orWhereHas('course', fn (Builder $course) => $course->whereRaw('LOWER(name) LIKE ?', [$term]));
                        });
                });
            })
            ->get()
            ->map(fn (Lesson $lesson): array => $this->lessonEvent($lesson))
            ->all();
    }

    /**
     * A lesson of a série reads as its course; a standalone one as its own name,
     * and they are tinted differently so the grid still tells them apart.
     *
     * @return array<string, mixed>
     */
    protected function lessonEvent(Lesson $lesson): array
    {
        $partOfSeries = $lesson->isPartOfSeries();
        [$accent, $tint] = $partOfSeries ? self::COURSE_COLORS : self::ONE_OFF_COLORS;
        $date = $lesson->lesson_date->toDateString();
        $title = $partOfSeries
            ? ($lesson->series?->course?->name ?? $lesson->series?->name ?? 'Lekce kurzu')
            : (string) $lesson->name;

        $event = EventData::make()
            ->id(($partOfSeries ? 'course:' : 'oneoff:').$lesson->getKey())
            ->title($title)
            ->start($date.'T'.$lesson->start_time)
            ->end($date.'T'.$lesson->end_time)
            ->backgroundColor($tint)
            ->borderColor($accent)
            ->textColor($partOfSeries ? '#155E75' : '#86198F')
            ->extendedProps([
                'kind' => $partOfSeries ? 'course' : 'oneOffEvent',
                'timeLabel' => $this->timeLabel($lesson->start_time, $lesson->end_time),
                'title' => $title,
                'series' => $lesson->series?->name,
                'category' => $lesson->category?->name,
                'room' => $lesson->room?->display_short_name,
                'instructorName' => $lesson->instructor?->name,
                'initials' => $this->therapistInitials($lesson->instructor?->name),
                'accent' => $accent,
                'occupancy' => $lesson->takenSpots().'/'.$lesson->capacity,
                'isUnpublished' => ! $partOfSeries && ! $lesson->isPublished(),
            ])
            ->extraProperties(['editable' => false]);

        if (! $this->selectionMode) {
            // The vendor JS navigates directly for events with a url and never
            // reaches onEventClick — read-only detail navigation. Omitted in
            // selection mode so clicks fall through unhandled.
            $event->url(LessonResource::getUrl('view', ['record' => $lesson]));
        }

        return $event->toArray();
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
        $roomIds = $this->scopedRoomIds();
        $start = substr((string) $info['start'], 0, 10);
        $end = substr((string) $info['end'], 0, 10);

        [$accent, $tint] = self::BLOCKING_COLORS;

        return RoomBlocking::query()
            ->with('room')
            ->where('is_recurring', false)
            ->whereNotNull('start_at')
            ->where('start_at', '<', $end.' 00:00:00')
            ->where('end_at', '>', $start.' 00:00:00')
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
                    'room' => $blocking->room?->display_short_name,
                ])
                ->toArray())
            ->all();
    }

    /**
     * Real-date working hours: dated work blocks in the visible range, plus
     * everything that already competes with them — room blockings (recurring
     * rows expanded onto their matching dates, one-off rows as-is) and course
     * lessons / one-off events, which occupy a room and often the therapist
     * too. Reservations are deliberately absent: this grid is the availability
     * template, not what has already been booked into it.
     *
     * @param  array{start: string, end: string, timezone: string}  $info
     */
    protected function fetchTemplateEvents(array $info): array
    {
        $start = Carbon::parse(substr((string) $info['start'], 0, 10));
        $end = Carbon::parse(substr((string) $info['end'], 0, 10));

        $events = [];

        $blocks = TherapistWorkBlock::query()
            ->with(['therapist.user', 'room'])
            ->whereBetween('work_date', [$start->toDateString(), $end->copy()->subDay()->toDateString()])
            ->when($this->therapistIds, fn (Builder $query) => $query->whereIn('therapist_id', $this->therapistIds))
            ->when($this->templateRoomId, fn (Builder $query) => $query->where('room_id', $this->templateRoomId))
            ->when($this->room, fn (Builder $query) => $query->where('room_id', $this->room->getKey()))
            ->get();

        foreach ($blocks as $block) {
            $accent = $this->therapistColor($block->therapist_id);
            $date = $block->work_date->toDateString();

            $events[] = EventData::make()
                ->id('schedule:'.$block->getKey())
                ->title((string) ($block->therapist?->user?->name ?? 'Pracovní doba'))
                ->start($date.'T'.$block->start_time)
                ->end($date.'T'.$block->end_time)
                ->backgroundColor($this->therapistTint($block->therapist_id))
                ->borderColor($accent)
                ->textColor('#171717')
                ->extendedProps([
                    'kind' => 'schedule',
                    'timeLabel' => $this->timeLabel($block->start_time, $block->end_time),
                    'therapistName' => $block->therapist?->user?->name,
                    'initials' => $this->therapistInitials($block->therapist?->user?->name),
                    'room' => $block->room?->display_short_name,
                    'accent' => $accent,
                    'isRecurring' => $block->series_id !== null,
                    'isSelected' => in_array('schedule:'.$block->getKey(), $this->selectedIds, true),
                ])
                ->toArray();
        }

        $blockings = RoomBlocking::query()
            ->with('room')
            ->where('is_recurring', true)
            ->when($this->templateRoomId, fn (Builder $query) => $query->where('room_id', $this->templateRoomId))
            ->when($this->room, fn (Builder $query) => $query->where('room_id', $this->room->getKey()))
            ->get();

        [$blockAccent, $blockTint] = self::BLOCKING_COLORS;

        foreach ($blockings as $blocking) {
            for ($day = $start->copy(); $day->lt($end); $day->addDay()) {
                if ($blocking->day_of_week !== DayOfWeek::fromCarbon($day) || ! $blocking->week_type->matchesDate($day)) {
                    continue;
                }

                $events[] = EventData::make()
                    ->id('blocking:'.$blocking->getKey())
                    ->title($blocking->reason ?: 'Blokace')
                    ->start($day->toDateString().'T'.$blocking->start_time)
                    ->end($day->toDateString().'T'.$blocking->end_time)
                    ->backgroundColor($blockTint)
                    ->borderColor($blockAccent)
                    ->textColor('#3730A3')
                    ->extendedProps([
                        'kind' => 'blocking',
                        'timeLabel' => $this->timeLabel($blocking->start_time, $blocking->end_time),
                        'title' => $blocking->reason ?: 'Blokace',
                        'room' => $blocking->room?->display_short_name,
                        'isSelected' => in_array('blocking:'.$blocking->getKey(), $this->selectedIds, true),
                    ])
                    ->toArray();
            }
        }

        return [
            ...$events,
            ...$this->fetchOneTimeBlockingEvents($info),
            ...$this->fetchLessonEvents($info),
        ];
    }

    protected function timeLabel(?string $start, ?string $end): string
    {
        return substr((string) $start, 0, 5).'–'.substr((string) $end, 0, 5);
    }

    /**
     * Pending day-waitlist entries in the visible week, grouped per day for the
     * day-column header badges. Honours therapist chips ("any therapist"
     * entries always count); other filters are reservation-specific and ignored.
     *
     * @return array<int, array{date: string, label: string, count: int, names: string}>
     */
    public function waitlistWeekSummary(): array
    {
        if ($this->isTemplateMode() || $this->room || ! $this->showWaitlist || ! Settings::dayWaitlistEnabled()) {
            return [];
        }

        $weekStart = $this->selectedDate()->startOfWeek(Carbon::MONDAY);

        return ReservationDayWaitlistEntry::query()
            ->pending()
            ->with(['client', 'therapist.user'])
            ->whereBetween('reservation_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->when($this->therapistIds, fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereIn('therapist_id', $this->therapistIds)->orWhereNull('therapist_id')
            ))
            ->orderBy('reservation_date')
            ->get()
            ->groupBy(fn (ReservationDayWaitlistEntry $entry): string => $entry->reservation_date->toDateString())
            ->map(fn (Collection $entries, string $date): array => [
                'date' => $date,
                'label' => Str::ucfirst(Carbon::parse($date)->locale('cs')->isoFormat('dd D. M.')),
                'count' => $entries->count(),
                'names' => $entries
                    ->map(fn (ReservationDayWaitlistEntry $entry): string => $entry->displayName().' ('.$entry->therapistLabel().')')
                    ->implode(', '),
            ])
            ->values()
            ->all();
    }

    /**
     * Waitlist badge data for the day-column headers, keyed by Y-m-d date.
     * Each badge links to the waitlist list pre-filtered to its day.
     *
     * @return array<string, array{count: int, url: string, tooltip: string}>
     */
    public function waitlistHeaderBadges(): array
    {
        $badges = [];

        foreach ($this->waitlistWeekSummary() as $day) {
            $badges[$day['date']] = [
                'count' => $day['count'],
                'url' => ReservationDayWaitlistResource::getUrl('index', [
                    'filters' => ['reservation_date' => ['value' => $day['date']]],
                ]),
                'tooltip' => 'V pořadníku: '.$day['count'],
            ];
        }

        return $badges;
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
        return Str::ucfirst($this->sidebarMonthStart()->locale('cs')->isoFormat('MMMM YYYY'));
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
     * Stats for the selected day, following the active therapist chip filter
     * (and, in working-hours mode, the toolbar room select).
     *
     * @return array{label: string, count: int, hours: string, free: string, utilization: int}
     */
    public function daySummary(): array
    {
        $date = $this->selectedDate();
        $roomId = $this->room?->getKey() ?? ($this->isTemplateMode() ? $this->templateRoomId : null);

        $reservations = Reservation::query()
            ->whereDate('reservation_date', $date->toDateString())
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->when($this->therapistIds, fn (Builder $query) => $query->whereIn('therapist_id', $this->therapistIds))
            ->when($roomId, fn (Builder $query) => $query->where('room_id', $roomId))
            ->get(['start_time', 'end_time']);

        $booked = (int) $reservations->sum(fn (Reservation $reservation): int => $this->minutesBetween($reservation->start_time, $reservation->end_time));
        $available = app(CalendarAvailability::class)->availableMinutes($date, $this->therapistIds, $roomId);

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

                if (p.kind === 'course' || p.kind === 'oneOffEvent') {
                    var croom = p.room ? '<span class="ff-event-room">' + esc(p.room) + '</span>' : '<span></span>';
                    var occ = p.occupancy ? '<span class="ff-event-tag" style="background:rgba(0,0,0,.06);color:inherit">' + esc(p.occupancy) + '</span>' : '';
                    var draft = p.isUnpublished ? '<span class="ff-event-tag" style="background:#F5F5F5;color:#525252">Koncept</span>' : '';
                    var cavatar = p.instructorName
                        ? '<span class="ff-event-avatar" title="' + esc(p.instructorName) + '" style="background:' + p.accent + '">' + esc(p.initials) + '</span>'
                        : '<span></span>';
                    return { html:
                        '<div class="ff-event ' + (p.kind === 'course' ? 'ff-event-course' : 'ff-event-oneoff') + '">' +
                            '<div class="ff-event-head"><span class="ff-event-time" style="color:' + p.accent + '">' + esc(p.timeLabel) + '</span>' + occ + draft + '</div>' +
                            '<div class="ff-event-title">' + esc(p.title) + '</div>' +
                            '<div class="ff-event-foot">' + cavatar + croom + '</div>' +
                        '</div>'
                    };
                }

                if (p.kind === 'schedule') {
                    var sroom = p.room ? '<span class="ff-event-room">' + esc(p.room) + '</span>' : '<span></span>';
                    var recur = p.isRecurring ? '<span class="ff-event-recur" title="Opakovaná pracovní doba">↻</span>' : '';
                    return { html:
                        '<div class="ff-event">' +
                            '<div class="ff-event-head"><span class="ff-event-time" style="color:' + p.accent + '">' + esc(p.timeLabel) + '</span>' + recur + '</div>' +
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
                // The break hangs below the card. Its height is a fraction of the
                // card's own, which in a timegrid is exactly its duration.
                var pause = p.hasBreak
                    ? '<div class="ff-event-break" style="height:calc(100% * ' + p.breakRatio + ')" title="' + esc(p.breakLabel) + ' · volno v ' + esc(p.breakUntil) + '">' +
                          '<span class="ff-event-break-label">' + esc(p.breakLabel) + '</span>' +
                      '</div>'
                    : '';
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
                    '</div>' + pause;

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
                if (p.hasBreak) { classes.push('ff-has-break'); }
                if (p.kind === 'blocking') { classes.push('ff-blocking'); }
                if (p.kind === 'course') { classes.push('ff-course'); }
                if (p.kind === 'oneOffEvent') { classes.push('ff-oneoff'); }
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
            // Control-therapy is pulled out of the form so it shares a row with the
            // calendar-only "Upozornit zákazníka" toggle.
            ...ReservationForm::components(withControlTherapy: false),
            Grid::make(2)->schema([
                ReservationForm::controlTherapyToggle(),
                // Create-only: a new booking's confirmation e-mail is opt-in here, but an
                // edit that changes the termín asks afterwards via the schedule-change
                // prompt ({@see reservationChangeNotifyAction}) instead of a toggle.
                Toggle::make('notify_client')
                    ->label('Upozornit zákazníka?')
                    ->helperText('Po uložení odešle zákazníkovi e-mail o vytvoření rezervace.')
                    ->default(true)
                    ->visible(fn (?Reservation $record): bool => ! (bool) $record?->exists),
            ]),
        ];
    }

    /**
     * Route event clicks: template blocks open their edit modal; reservations
     * open the edit modal (or toggle selection in selection mode). Lessons are
     * read-only in both modes and only ever navigate.
     *
     * @param  array<string, mixed>  $event
     */
    public function onEventClick(array $event): void
    {
        $eventId = (string) ($event['id'] ?? '');

        if ($eventId === '') {
            return;
        }

        // Course lessons and one-off events are read-only overlays in both
        // modes. The per-event url normally handles the click in JS; this
        // covers selection mode (no url set) and any stray Livewire
        // round-trips, and keeps lesson ids out of the template edit modals
        // and out of the bulk selection.
        if (str_starts_with($eventId, 'course:') || str_starts_with($eventId, 'oneoff:')) {
            if (! $this->selectionMode) {
                $this->redirect(LessonResource::getUrl('view', ['record' => explode(':', $eventId, 2)[1]]));
            }

            return;
        }

        if ($this->isTemplateMode()) {
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
                // Both recurring and one-off blockings render in this mode.
                $isRecurring = (bool) RoomBlocking::find($id)?->is_recurring;

                $this->mountAction($isRecurring ? 'editBlocking' : 'editOneTimeBlocking');
            }

            return;
        }

        $id = $eventId;
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
                ->modalSubmitActionLabel('Vytvořit a zavřít')
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->after(function (FullCalendarWidget $livewire, ?Model $record, array $data): void {
                    if ($record instanceof Reservation && ($data['notify_client'] ?? false)) {
                        $record->client?->notify(new ReservationNotification($record, 'created'));

                        SentEmailReceipt::forCurrentUser('Nová rezervace');
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
                // The closure parameter must be named $action so Filament injects
                // the built submit button (any other name yields the parent action).
                ->modalFooterActionsAlignment(Alignment::Between)
                ->modalSubmitAction(fn (Action $action) => $action
                    ->icon('lucide-save')
                    ->extraAttributes(['style' => 'order:2;margin-inline-start:auto;']))
                ->before(function (Reservation $record): void {
                    // Snapshot the original values before the edit is saved so the
                    // "reservation changed" e-mail can show the puvodni_* tokens.
                    $this->reservationChangeSnapshot = ReservationChangeSnapshot::capture($record);
                })
                ->extraModalFooterActions([
                    // The same cancel/erase modal as the reservations list — bound
                    // to the widget's record like Saade's DeleteAction, closing the
                    // edit modal and refreshing the calendar afterwards.
                    CancelReservationAction::make()
                        ->extraAttributes(['style' => 'order:1'])
                        ->model(fn (FullCalendarWidget $livewire): ?string => $livewire->getModel())
                        ->record(fn (FullCalendarWidget $livewire): ?Model => $livewire->getRecord())
                        ->cancelParentActions()
                        ->after(function (FullCalendarWidget $livewire): void {
                            $livewire->record = null;
                            $livewire->refreshRecords();
                        }),
                    // The same restore/reactivate modal as the reservations list —
                    // shown for trashed or cancelled reservations, bound to the
                    // widget's record like Saade's DeleteAction, closing the edit
                    // modal and refreshing the calendar afterwards.
                    RestoreReservationAction::make()
                        ->extraAttributes(['style' => 'order:1'])
                        ->model(fn (FullCalendarWidget $livewire): ?string => $livewire->getModel())
                        ->record(fn (FullCalendarWidget $livewire): ?Model => $livewire->getRecord())
                        ->cancelParentActions()
                        ->after(function (FullCalendarWidget $livewire): void {
                            $livewire->record = null;
                            $livewire->refreshRecords();
                        }),
                ])
                ->after(function (FullCalendarWidget $livewire, Model $record, array $data): void {
                    if ($record instanceof Reservation) {
                        LogActivity::record('reservation_edited', $record, 'Rezervace upravena', [
                            'source' => 'Kalendář',
                        ]);

                        // A termín change (date/time, room or therapist) asks whether to
                        // notify the client + therapist via the follow-up prompt, carrying
                        // the record and the pre-edit snapshot as mount arguments.
                        if ($record->wasChanged(Reservation::SCHEDULE_ATTRIBUTES)) {
                            $livewire->replaceMountedAction('reservationChangeNotify', [
                                'record' => (string) $record->getKey(),
                                'snapshot' => $this->reservationChangeSnapshot,
                            ]);
                        }
                    }

                    $livewire->refreshRecords();
                }),
        ];
    }

    /**
     * The follow-up prompt opened after an edit that changes a reservation's termín:
     * asks whether to e-mail the client and therapist about the change and lets staff
     * attach an optional message. Chained in via replaceMountedAction from the edit
     * action's after(), with the record id + pre-edit snapshot as mount arguments so
     * they survive the extra round-trip.
     */
    public function reservationChangeNotifyAction(): Action
    {
        return ScheduleChangeNotificationPrompt::make(
            'reservationChangeNotify',
            'zákazníka a terapeuta',
            function (?string $reason, array $arguments): int {
                $reservation = Reservation::with(['client', 'service', 'therapist.user'])
                    ->find($arguments['record'] ?? null);

                if ($reservation === null) {
                    return 0;
                }

                return app(NotifyReservationChange::class)(
                    $reservation,
                    is_array($arguments['snapshot'] ?? null) ? $arguments['snapshot'] : [],
                    $reason,
                );
            },
        );
    }
}
