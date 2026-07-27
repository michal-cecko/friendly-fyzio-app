<?php

namespace App\Support\Suggestions\Rules;

use App\Support\Suggestions\SuggestionRule;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Base for the rules that collapse an unbounded backlog into a single card
 * carrying a count and a link to the pre-filtered list. Subclasses supply the
 * query and the copy; counting, capping and the "no inline resolve" answer are
 * the same for all of them.
 */
abstract class AggregateRule implements SuggestionRule
{
    /**
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    abstract protected function query(): Builder;

    /**
     * @return list<array<string, mixed>> exactly one card, built from the count
     */
    abstract protected function card(int $count): array;

    public function isEnabled(): bool
    {
        return true;
    }

    public function count(int $cap): int
    {
        return $this->isEnabled() && $this->query()->exists() ? 1 : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $cap): array
    {
        if (! $this->isEnabled() || $cap < 1) {
            return [];
        }

        $count = $this->query()->count();

        return $count === 0 ? [] : $this->card($count);
    }

    public function resolve(?string $id): string
    {
        throw new LogicException("Návrh {$this->type()} se nedá vyřídit jedním kliknutím.");
    }
}
