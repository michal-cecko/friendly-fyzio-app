<?php

namespace App\Livewire;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferVisibility;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\OneOffEvent;
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
 * listed run surface once in the muted "Připravujeme" tail. One-off events
 * moved to their own category pages — an encouragement section under the grid
 * cross-sells course-derived events ("try a single session first"). Rendered
 * by the course-archive CMS brick.
 */
class CourseArchive extends Component
{
    use WithPagination;

    /** @var array<string, mixed> */
    public array $config = [];

    #[Url(as: 'kategorie')]
    public ?string $category = null;

    #[Url(as: 'dostupne')]
    public bool $availableOnly = false;

    #[Url(as: 'hledani')]
    public string $search = '';

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

    public function selectCategory(?string $slug): void
    {
        $this->category = $slug;
        $this->resetPage('strana');
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
        return view('livewire.course-archive', [
            'categories' => $this->categories(),
            'results' => $this->courses(),
            'preparing' => $this->preparingCourses(),
            'showFilters' => (bool) ($this->config['show_filters'] ?? true),
            'showSearch' => (bool) ($this->config['show_search'] ?? true),
            'filtersActive' => $this->category !== null || filled($this->search) || $this->availableOnly,
            'crossSell' => $this->crossSell(),
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
     * The "try a single session first" encouragement under the course grid:
     * configured copy, up to three upcoming course-derived events and a CTA to
     * the configured event category page. Suppressed on filtered/paged views
     * (mirrors the preparing tail) and via the brick toggle.
     *
     * @return array{title: string, text: string, url: ?string, events: Collection<int, OneOffEvent>}|null
     */
    protected function crossSell(): ?array
    {
        if (! ($this->config['cross_sell'] ?? true)) {
            return null;
        }

        if ($this->availableOnly || filled($this->search) || (int) ($this->paginators['strana'] ?? 1) > 1) {
            return null;
        }

        $categorySlug = (string) ($this->config['cross_sell_category'] ?? 'jednorazove-lekce');
        $category = EventCategory::query()->published()->where('slug', $categorySlug)->first();

        $events = OneOffEvent::query()
            ->published()
            ->upcoming()
            ->where('visibility', OfferVisibility::Public)
            ->whereNotNull('course_id')
            ->whereHas('course', fn (Builder $course) => $course->published())
            ->withCount('activeTakers')
            ->with(['course', 'category'])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(3)
            ->get();

        if ($category === null && $events->isEmpty()) {
            return null;
        }

        return [
            'title' => (string) ($this->config['cross_sell_title'] ?? 'Chcete si to nejdřív vyzkoušet?'),
            'text' => (string) ($this->config['cross_sell_text'] ?? 'Přijďte na jednorázovou lekci bez závazku celého kurzu.'),
            'url' => $category?->permalink,
            'events' => $events,
        ];
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
