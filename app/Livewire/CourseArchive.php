<?php

namespace App\Livewire;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Support\Offers\EventArchiveQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public "Pohybové kurzy" archive: category pills, an availability toggle,
 * text search and pagination — every filter lives in the query string so
 * results are shareable/deep-linkable. The grid shows one card per LISTED
 * SERIES (public, open-or-full run), so a course with two upcoming terms
 * appears twice and each card links straight to its term; courses without a
 * listed run surface once in the muted "Připravujeme" tail.
 *
 * With the brick-level `show_type_switch` on, two cards above the filters
 * switch the same archive over to one-off events (`?typ=lekce`) — scoped to
 * the configured event categories, muted "Proběhlé akce" tail included. The
 * two tabs use different taxonomies (CourseCategory vs EventCategory), so
 * switching always clears the category pill. Rendered by the course-archive
 * CMS brick.
 */
class CourseArchive extends Component
{
    use WithPagination;

    /** @var array<string, mixed> */
    public array $config = [];

    #[Url(as: 'typ')]
    public string $type = 'kurzy';

    #[Url(as: 'kategorie')]
    public ?string $category = null;

    #[Url(as: 'dostupne')]
    public bool $availableOnly = false;

    #[Url(as: 'hledani')]
    public string $search = '';

    public function updatedType(): void
    {
        $this->category = null;
        $this->resetPage('strana');
    }

    public function updatedCategory(): void
    {
        $this->resetPage('strana');
    }

    public function updatedAvailableOnly(): void
    {
        $this->resetPage('strana');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('strana');
    }

    public function selectType(string $type): void
    {
        $this->type = $type === 'lekce' ? 'lekce' : 'kurzy';
        $this->category = null;
        $this->resetPage('strana');
    }

    public function selectCategory(?string $slug): void
    {
        $this->category = $slug;
        $this->resetPage('strana');
    }

    /**
     * Whether the events tab is the one being shown. Without the brick toggle
     * the archive is courses-only, so a stray `?typ=lekce` is ignored.
     */
    protected function showsEvents(): bool
    {
        return $this->showTypeSwitch() && $this->type === 'lekce';
    }

    protected function showTypeSwitch(): bool
    {
        return (bool) ($this->config['show_type_switch'] ?? false);
    }

    /**
     * Whether the current viewer may see private/invite-only offers in the
     * archive: a logged-in customer (not staff — staff preview is separate).
     */
    protected function includePrivate(): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->isStaff();
    }

    public function render(): View
    {
        $events = $this->showsEvents();

        return view('livewire.course-archive', [
            'isEvents' => $events,
            'categories' => $events ? $this->eventCategories() : $this->categories(),
            'results' => $events ? $this->events() : $this->courses(),
            'preparing' => $events ? new Collection : $this->preparingCourses(),
            'past' => $events ? $this->pastEvents() : new Collection,
            'showFilters' => (bool) ($this->config['show_filters'] ?? true),
            'showSearch' => (bool) ($this->config['show_search'] ?? true),
            'showTypeSwitch' => $this->showTypeSwitch(),
            'coursesLabel' => (string) ($this->config['courses_label'] ?? 'Pohybové kurzy'),
            'coursesSubtitle' => (string) ($this->config['courses_subtitle'] ?? 'Pravidelné semestrální série lekcí'),
            'eventsLabel' => (string) ($this->config['events_label'] ?? 'Jednorázové lekce'),
            'eventsSubtitle' => (string) ($this->config['events_subtitle'] ?? 'Jednotlivé lekce bez závazku'),
            'filtersActive' => $this->category !== null || filled($this->search) || $this->availableOnly,
        ]);
    }

    /**
     * @return Collection<int, CourseCategory>
     */
    protected function categories(): Collection
    {
        return CourseCategory::query()
            ->published()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * One row per listed run: publicly visible, open or full (full runs keep
     * their card with the waitlist CTA), not yet ended, on a published course.
     * "Jenom dostupné" additionally requires an Open status and a free spot.
     */
    protected function courses(): mixed
    {
        return CourseSeries::query()
            ->with('course.category')
            ->withCount('activeTakers')
            ->withCount('lessons')
            ->withCount(['lessons as remaining_lessons_count' => fn ($lessons) => $lessons->whereDate('lesson_date', '>=', today())])
            ->when(! $this->includePrivate(), fn (Builder $query) => $query->where('visibility', CourseSeriesVisibility::Public))
            ->whereIn('status', [CourseSeriesStatus::Open, CourseSeriesStatus::Full])
            ->whereDate('end_date', '>=', today())
            ->whereHas('course', fn (Builder $course) => $course->published())
            ->when($this->category, fn (Builder $query, string $slug) => $query
                ->whereHas('course.category', fn (Builder $category) => $category->where('slug', $slug)))
            ->when(filled($this->search), fn (Builder $query) => $query
                ->whereHas('course', fn (Builder $course) => $this->applySearch($course, ['name', 'description'])))
            ->when($this->availableOnly, fn (Builder $query) => $query
                ->where('status', CourseSeriesStatus::Open)
                ->hasSpotsLeft()
                ->withoutActiveWaitlistInvite())
            ->orderBy('start_date')
            ->orderBy('id')
            ->paginate(6, pageName: 'strana');
    }

    /**
     * Published courses with no publicly listed run — the muted "Připravujeme"
     * tail under the grid (their detail pages collect interest e-mails). Only
     * on the first page of an unfiltered/-searched listing, mirroring the
     * event archive's past section.
     *
     * @return Collection<int, Course>
     */
    protected function preparingCourses(): Collection
    {
        if ($this->availableOnly || filled($this->search) || (int) ($this->paginators['strana'] ?? 1) > 1) {
            return new Collection;
        }

        return Course::query()
            ->published()
            ->with('category')
            ->when($this->category, fn (Builder $query, string $slug) => $query
                ->whereHas('category', fn (Builder $category) => $category->where('slug', $slug)))
            ->whereDoesntHave('series', fn (Builder $series) => $series
                ->when(! $this->includePrivate(), fn (Builder $query) => $query->where('visibility', CourseSeriesVisibility::Public))
                ->whereIn('status', [CourseSeriesStatus::Open, CourseSeriesStatus::Full])
                ->whereDate('end_date', '>=', today()))
            ->orderBy('name')
            ->get();
    }

    /**
     * Upcoming one-off events for the "Jednorázové lekce" tab, narrowed to the
     * brick-configured categories. Mirrors the standalone event archive.
     */
    protected function events(): mixed
    {
        return EventArchiveQuery::base($this->activeEventCategorySlugs(), $this->includePrivate())
            ->upcoming()
            ->when(filled($this->search), fn (Builder $query) => EventArchiveQuery::applySearch($query, $this->search))
            ->when($this->availableOnly, fn (Builder $query) => $query
                ->hasSpotsLeft()
                ->withoutActiveWaitlistInvite())
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->paginate(6, pageName: 'strana');
    }

    /**
     * Recently held events, shown muted as information — the events-tab twin of
     * the courses tab's "Připravujeme" tail, suppressed under the same rules.
     *
     * @return Collection<int, Lesson>
     */
    protected function pastEvents(): Collection
    {
        if ($this->availableOnly || filled($this->search) || (int) ($this->paginators['strana'] ?? 1) > 1) {
            return new Collection;
        }

        return EventArchiveQuery::base($this->activeEventCategorySlugs(), $this->includePrivate())
            ->past()
            ->orderByDesc('lesson_date')
            ->limit(3)
            ->get();
    }

    /**
     * The categories actually queried right now: a selected pill narrows the
     * configured set down to itself.
     *
     * @return array<int, string>
     */
    protected function activeEventCategorySlugs(): array
    {
        $configured = $this->eventCategorySlugs();

        if ($this->category === null) {
            return $configured;
        }

        // Guard against a stale `?kategorie=` from the courses tab: an unknown
        // slug must not widen the archive past its configured categories.
        if ($configured !== [] && ! in_array($this->category, $configured, true)) {
            return $configured;
        }

        return [$this->category];
    }

    /**
     * Category pills for the events tab: the configured categories, or every
     * published one when nothing is configured. A single category needs no
     * pills — the tab is then pinned to it.
     *
     * @return Collection<int, EventCategory>
     */
    protected function eventCategories(): Collection
    {
        $slugs = $this->eventCategorySlugs();

        if (count($slugs) === 1) {
            return new Collection;
        }

        return EventCategory::query()
            ->published()
            ->when($slugs !== [], fn (Builder $query) => $query->whereIn('slug', $slugs))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * The event categories the tab covers. An empty list means every category;
     * a single one pins the tab to it. Tolerates a bare string, so a config
     * written by the old single-select shape keeps working.
     *
     * @return array<int, string>
     */
    protected function eventCategorySlugs(): array
    {
        $configured = $this->config['event_categories'] ?? [];

        if (is_string($configured)) {
            $configured = [$configured];
        }

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($slug): string => (string) $slug, $configured),
            fn (string $slug): bool => $slug !== '',
        ));
    }

    /**
     * Cross-database case-insensitive search over the given columns.
     *
     * @param  array<int, string>  $columns
     */
    protected function applySearch(Builder $query, array $columns): Builder
    {
        $needle = '%'.mb_strtolower(trim($this->search)).'%';

        return $query->where(function (Builder $inner) use ($columns, $needle): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw("LOWER({$column}) LIKE ?", [$needle]);
            }
        });
    }
}
