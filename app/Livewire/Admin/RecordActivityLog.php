<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\ActivityLog\ActivityPresenter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * The body of the "Historie změn" modal (see ActivityLogAction): the activity
 * log of a single record with quicksearch, Akce/Kdo/date filters and
 * pagination. Lives in a modal, so pagination is deliberately NOT synced to
 * the query string.
 */
class RecordActivityLog extends Component implements HasSchemas
{
    use InteractsWithSchemas;
    use WithoutUrlPagination;
    use WithPagination;

    /** @var list<string> */
    private const FILTERS = ['search', 'event', 'causer', 'from', 'to'];

    private const MAX_RELATED_IDS = 500;

    /** Morph class of the record whose history this is. */
    public string $subjectType;

    public string $subjectId;

    /**
     * Records whose history belongs on this one's page too — a lesson's log
     * carries every presence event of its roster, each of which is filed
     * against the seat it changed rather than the lesson.
     *
     * @var array<int, array{type: string, ids: array<int, string>}>
     */
    public array $relatedSubjects = [];

    public string $search = '';

    public ?string $event = null;

    /** A user UUID, or 'system' for activity without a causer. */
    public ?string $causer = null;

    public ?string $from = null;

    public ?string $to = null;

    /**
     * @param  array<int, array{type?: string, ids?: array<int, string>}>  $relatedSubjects
     */
    public function mount(string $subjectType, string $subjectId, array $relatedSubjects = []): void
    {
        abort_unless(auth()->user()?->isStaff() ?? false, 403);

        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->relatedSubjects = collect($relatedSubjects)
            ->filter(fn (array $related): bool => filled($related['type'] ?? null) && filled($related['ids'] ?? null))
            ->map(fn (array $related): array => [
                'type' => (string) $related['type'],
                // A roster is a few dozen rows; the cap is only here so a
                // pathological owner cannot turn this into an unbounded IN().
                'ids' => array_slice(array_values(array_map('strval', $related['ids'])), 0, self::MAX_RELATED_IDS),
            ])
            ->values()
            ->all();
    }

    /**
     * The Akce / Kdo / date filters, as real Filament fields: searchable
     * non-native selects and Filament's own date picker rather than the raw
     * browser controls.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('event')
                    ->label('Akce')
                    ->placeholder('Všechny akce')
                    ->options(fn (): array => $this->eventOptions())
                    ->visible(fn (): bool => filled($this->eventOptions()))
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),
                Select::make('causer')
                    ->label('Kdo')
                    ->placeholder('Kdokoliv')
                    ->options(fn (): array => $this->causerOptions())
                    ->visible(fn (): bool => filled($this->causerOptions()))
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),
                DatePicker::make('from')
                    ->label('Od data')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->maxDate(fn (): ?string => $this->to)
                    ->closeOnDateSelection()
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),
                DatePicker::make('to')
                    ->label('Do data')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->minDate(fn (): ?string => $this->from)
                    ->closeOnDateSelection()
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),
            ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(self::FILTERS);
        $this->resetPage();
    }

    /** Removes a single filter from its indicator chip. */
    public function clearFilter(string $filter): void
    {
        if (! in_array($filter, self::FILTERS, true)) {
            return;
        }

        $this->reset($filter);
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return $this->activeFilters() !== [];
    }

    public function render(): View
    {
        $eventOptions = $this->eventOptions();
        $causerOptions = $this->causerOptions();

        return view('filament.activity.record-log', [
            'activities' => $this->activities(),
            'eventOptions' => $eventOptions,
            'causerOptions' => $causerOptions,
            'indicators' => $this->indicators($eventOptions, $causerOptions),
            'filtersActive' => $this->hasActiveFilters(),
            // The search box sits outside the panel, so it is not counted on its badge.
            'panelFilterCount' => count(array_diff($this->activeFilters(), ['search'])),
        ]);
    }

    /**
     * Removable chips describing what is currently narrowing the list, phrased
     * like the standalone log table's filter indicators.
     *
     * @param  array<string, string>  $eventOptions
     * @param  array<string, string>  $causerOptions
     * @return list<array{key: string, label: string}>
     */
    protected function indicators(array $eventOptions, array $causerOptions): array
    {
        $labels = [
            'search' => fn (): string => 'Hledání: '.$this->search,
            'event' => fn (): string => 'Akce: '.($eventOptions[$this->event] ?? ActivityPresenter::eventLabel($this->event)),
            'causer' => fn (): string => 'Kdo: '.($causerOptions[$this->causer] ?? 'Neznámý'),
            'from' => fn (): string => 'Od '.Carbon::parse($this->from)->format('d.m.Y'),
            'to' => fn (): string => 'Do '.Carbon::parse($this->to)->format('d.m.Y'),
        ];

        return array_values(array_map(
            fn (string $filter): array => ['key' => $filter, 'label' => $labels[$filter]()],
            $this->activeFilters(),
        ));
    }

    /** @return list<string> */
    protected function activeFilters(): array
    {
        return array_values(array_filter(self::FILTERS, fn (string $filter): bool => filled($this->{$filter})));
    }

    protected function activities(): mixed
    {
        return $this->baseQuery()
            ->with(['causer', 'subject'])
            ->when(filled($this->search), fn (Builder $query): Builder => $this->applySearch($query))
            ->when($this->event, fn (Builder $query, string $event): Builder => $query->where('event', $event))
            ->when($this->causer === 'system', fn (Builder $query): Builder => $query->whereNull('causer_id'))
            ->when(filled($this->causer) && $this->causer !== 'system',
                fn (Builder $query): Builder => $query->where('causer_id', $this->causer))
            ->when($this->from, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($this->to, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate(10);
    }

    /**
     * Every activity logged for this record, plus anything logged for the
     * records it stands in for. The whole set is wrapped in one group so the
     * filters below AND against all of it rather than only the last branch.
     */
    protected function baseQuery(): Builder
    {
        return Activity::query()->where(function (Builder $query): void {
            $query->where(fn (Builder $own): Builder => $own
                ->where('subject_type', $this->subjectType)
                ->where('subject_id', $this->subjectId));

            foreach ($this->relatedSubjects as $related) {
                $query->orWhere(fn (Builder $other): Builder => $other
                    ->where('subject_type', $related['type'])
                    ->whereIn('subject_id', $related['ids']));
            }
        });
    }

    /**
     * Mirrors the standalone log table's search: the title snapshot plus the
     * raw logged payloads. LOWER() keeps it case-insensitive on every driver;
     * Postgres has no lower(json), so the payload columns are cast to text.
     */
    protected function applySearch(Builder $query): Builder
    {
        $needle = '%'.mb_strtolower(trim($this->search)).'%';
        $castsJson = $query->getConnection()->getDriverName() === 'pgsql';

        return $query->where(function (Builder $inner) use ($needle, $castsJson): void {
            foreach (['description', 'attribute_changes', 'properties'] as $column) {
                $expression = $castsJson ? "CAST({$column} AS TEXT)" : $column;

                $inner->orWhereRaw("LOWER({$expression}) LIKE ?", [$needle]);
            }
        });
    }

    /**
     * Only the events this record actually has, so the select never offers a
     * filter that yields nothing.
     *
     * @return array<string, string>
     */
    protected function eventOptions(): array
    {
        return $this->baseQuery()
            ->whereNotNull('event')
            ->distinct()
            ->pluck('event')
            ->mapWithKeys(fn (string $event): array => [$event => ActivityPresenter::eventLabel($event)])
            ->sort()
            ->all();
    }

    /**
     * The people who touched this record, plus a "Systém / online" entry when
     * some activity has no causer.
     *
     * @return array<string, string>
     */
    protected function causerOptions(): array
    {
        $causerIds = $this->baseQuery()->whereNotNull('causer_id')->distinct()->pluck('causer_id');

        $options = User::query()
            ->whereIn('id', $causerIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        if ($this->baseQuery()->whereNull('causer_id')->exists()) {
            $options['system'] = 'Systém / online';
        }

        return $options;
    }
}
