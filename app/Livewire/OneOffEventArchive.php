<?php

namespace App\Livewire;

use App\Enums\OfferVisibility;
use App\Models\EventCategory;
use App\Models\OneOffEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public archive of one-off events (workshopy, jednorázové lekce, …):
 * upcoming published events with live capacity, an availability toggle,
 * optional text search (query-string bound) and a muted "already happened"
 * tail per spec §3.6. Rendered by the event-archive CMS brick — a brick-level
 * `category` slug pre-filters the archive for the category landing pages;
 * without it, URL-bound category pills switch between all categories.
 */
class OneOffEventArchive extends Component
{
    use WithPagination;

    /** @var array<string, mixed> */
    public array $config = [];

    #[Url(as: 'kategorie')]
    public ?string $category = null;

    #[Url(as: 'hledani')]
    public string $search = '';

    #[Url(as: 'dostupne')]
    public bool $availableOnly = false;

    public function updatedSearch(): void
    {
        $this->resetPage('strana');
    }

    public function updatedAvailableOnly(): void
    {
        $this->resetPage('strana');
    }

    public function selectCategory(?string $slug): void
    {
        $this->category = $slug;
        $this->resetPage('strana');
    }

    public function render(): View
    {
        $upcoming = $this->baseQuery()
            ->upcoming()
            ->when(filled($this->search), fn (Builder $query) => $this->applySearch($query))
            ->when($this->availableOnly, fn (Builder $query) => $query
                ->hasSpotsLeft()
                ->withoutActiveWaitlistInvite())
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->paginate(6, pageName: 'strana');

        return view('livewire.one-off-event-archive', [
            'upcoming' => $upcoming,
            'past' => $this->past(),
            'categories' => $this->selectableCategories(),
            'fixedCategory' => $this->fixedCategory(),
            'showSearch' => (bool) ($this->config['show_search'] ?? true),
        ]);
    }

    /**
     * The brick-configured category slug pinning this archive instance to one
     * category (the category landing pages), or null for the switchable view.
     */
    protected function fixedCategory(): ?string
    {
        $slug = $this->config['category'] ?? null;

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    protected function baseQuery(): Builder
    {
        $categorySlug = $this->fixedCategory() ?? $this->category;

        return OneOffEvent::query()
            ->published()
            ->when(! $this->includePrivate(), fn (Builder $query) => $query->where('visibility', OfferVisibility::Public))
            ->when($categorySlug, fn (Builder $query, string $slug) => $query
                ->whereHas('category', fn (Builder $category) => $category->where('slug', $slug)))
            ->withCount('activeTakers')
            ->with(['room', 'category', 'course']);
    }

    /**
     * Category pills — only when the brick isn't pinned to a single category.
     *
     * @return Collection<int, EventCategory>
     */
    protected function selectableCategories(): Collection
    {
        if ($this->fixedCategory() !== null) {
            return new Collection;
        }

        return EventCategory::query()
            ->published()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Recently held events, shown muted as information ("bežne poriadame, ale
     * aktuálne nie sú vypsané") — only on the first, unfiltered page.
     *
     * @return Collection<int, OneOffEvent>
     */
    protected function past(): Collection
    {
        if (! ($this->config['show_past'] ?? true) || $this->availableOnly || filled($this->search) || (int) ($this->paginators['strana'] ?? 1) > 1) {
            return new Collection;
        }

        return $this->baseQuery()
            ->past()
            ->orderByDesc('event_date')
            ->limit(3)
            ->get();
    }

    /**
     * Whether the current viewer may see private/invite-only events in the
     * archive: a logged-in customer (staff preview is handled separately).
     */
    protected function includePrivate(): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->isStaff();
    }

    /**
     * Case-insensitive search over the event's own name/description with a
     * fallback to the linked course's name (lesson-type events often derive
     * their content from the course).
     */
    protected function applySearch(Builder $query): Builder
    {
        $needle = '%'.mb_strtolower(trim($this->search)).'%';

        return $query->where(fn (Builder $inner) => $inner
            ->whereRaw('LOWER(name) LIKE ?', [$needle])
            ->orWhereRaw('LOWER(description) LIKE ?', [$needle])
            ->orWhereHas('course', fn (Builder $course) => $course
                ->whereRaw('LOWER(name) LIKE ?', [$needle])));
    }
}
