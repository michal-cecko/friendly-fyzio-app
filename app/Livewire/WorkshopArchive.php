<?php

namespace App\Livewire;

use App\Enums\OfferVisibility;
use App\Models\Workshop;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public "Workshopy" archive: upcoming published workshops with live
 * capacity, an availability toggle, optional text search (both query-string
 * bound) and a muted "already happened" tail per spec §3.6. Rendered by the
 * workshop-archive CMS brick.
 */
class WorkshopArchive extends Component
{
    use WithPagination;

    /** @var array<string, mixed> */
    public array $config = [];

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

    public function render(): View
    {
        $upcoming = Workshop::query()
            ->published()
            ->upcoming()
            ->when(! $this->includePrivate(), fn (Builder $query) => $query->where('visibility', OfferVisibility::Public))
            ->withCount('activeTakers')
            ->with('room')
            ->when(filled($this->search), fn (Builder $query) => $this->applySearch($query))
            ->when($this->availableOnly, fn (Builder $query) => $query->hasSpotsLeft())
            ->orderBy('workshop_date')
            ->orderBy('start_time')
            ->paginate(6, pageName: 'strana');

        return view('livewire.workshop-archive', [
            'upcoming' => $upcoming,
            'past' => $this->past(),
            'showSearch' => (bool) ($this->config['show_search'] ?? true),
        ]);
    }

    /**
     * Recently held workshops, shown muted as information ("bežne poriadame,
     * ale aktuálne nie sú vypsané") — only on the first, unfiltered page.
     *
     * @return Collection<int, Workshop>
     */
    protected function past(): Collection
    {
        if (! ($this->config['show_past'] ?? true) || $this->availableOnly || filled($this->search) || (int) ($this->paginators['strana'] ?? 1) > 1) {
            return new Collection;
        }

        return Workshop::query()
            ->published()
            ->past()
            ->when(! $this->includePrivate(), fn (Builder $query) => $query->where('visibility', OfferVisibility::Public))
            ->withCount('activeTakers')
            ->orderByDesc('workshop_date')
            ->limit(3)
            ->get();
    }

    /**
     * Whether the current viewer may see private/invite-only workshops in the
     * archive: a logged-in customer (staff preview is handled separately).
     */
    protected function includePrivate(): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->isStaff();
    }

    protected function applySearch(Builder $query): Builder
    {
        $needle = '%'.mb_strtolower(trim($this->search)).'%';

        return $query->where(fn (Builder $inner) => $inner
            ->whereRaw('LOWER(name) LIKE ?', [$needle])
            ->orWhereRaw('LOWER(description) LIKE ?', [$needle]));
    }
}
