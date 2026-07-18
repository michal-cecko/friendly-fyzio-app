<?php

namespace App\Livewire;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferVisibility;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public "Pohybové kurzy" archive: a switcher between semester courses and
 * one-time lessons, category pills, an availability toggle, text search and
 * pagination — every filter lives in the query string so results are
 * shareable/deep-linkable. The kurzy tab shows one card per LISTED SERIES
 * (public, open-or-full run), so a course with two upcoming terms appears
 * twice and each card links straight to its term; courses without a listed
 * run surface once in the muted "Připravujeme" tail. Rendered by the
 * course-archive CMS brick.
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
        $this->type = in_array($type, ['kurzy', 'lekce'], true) ? $type : 'kurzy';
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
        $lessons = $this->type === 'lekce';

        return view('livewire.course-archive', [
            'categories' => $this->categories(),
            'results' => $lessons ? $this->lessons() : $this->courses(),
            'preparing' => $lessons ? new Collection : $this->preparingCourses(),
            'showFilters' => (bool) ($this->config['show_filters'] ?? true),
            'showSearch' => (bool) ($this->config['show_search'] ?? true),
            'showTypeSwitch' => (bool) ($this->config['show_type_switch'] ?? true),
            'openCoursesCount' => Course::query()->published()
                ->whereHas('series', fn (Builder $series) => $series
                    ->where('status', CourseSeriesStatus::Open)
                    ->when(! $this->includePrivate(), fn (Builder $query) => $query->where('visibility', CourseSeriesVisibility::Public))
                    ->whereDate('end_date', '>=', today()))
                ->count(),
            'upcomingLessonsCount' => OneTimeLesson::query()->published()->upcoming()
                ->when(! $this->includePrivate(), fn (Builder $series) => $series->where('visibility', OfferVisibility::Public))
                ->whereHas('course', fn (Builder $course) => $course->published())
                ->count(),
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
                ->hasSpotsLeft())
            ->orderBy('start_date')
            ->orderBy('id')
            ->paginate(6, pageName: 'strana');
    }

    /**
     * Published courses with no publicly listed run — the muted "Připravujeme"
     * tail under the grid (their detail pages collect interest e-mails). Only
     * on the first page of an unfiltered/-searched listing, mirroring the
     * workshop archive's past section.
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

    protected function lessons(): mixed
    {
        return OneTimeLesson::query()
            ->published()
            ->upcoming()
            ->when(! $this->includePrivate(), fn (Builder $query) => $query->where('visibility', OfferVisibility::Public))
            ->withCount('activeTakers')
            ->whereHas('course', fn (Builder $course) => $course->published())
            ->with(['course.category', 'room'])
            ->when($this->category, fn (Builder $query, string $slug) => $query
                ->whereHas('course.category', fn (Builder $category) => $category->where('slug', $slug)))
            ->when(filled($this->search), fn (Builder $query) => $query
                ->whereHas('course', fn (Builder $course) => $this->applySearch($course, ['name', 'description'])))
            ->when($this->availableOnly, fn (Builder $query) => $query->hasSpotsLeft())
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->paginate(6, pageName: 'strana');
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
