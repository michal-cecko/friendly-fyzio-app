<?php

namespace App\Support\Reservations;

use App\Enums\ConflictSeverity;

/**
 * Two things competing for the same room or the same person at the same time,
 * as reported by {@see ConflictFinder}.
 *
 * The Czech headline is computed here rather than in the view: a conflict pairs
 * four kinds of record across two dimensions, and a card should render a
 * sentence, not decide one.
 */
final class Conflict
{
    public function __construct(
        public readonly string $type,               // 'room' | 'therapist'
        public readonly ConflictSeverity $severity,
        public readonly string $title,
        public readonly string $shared,             // the contested room or person
        public readonly string $date,               // 'Y-m-d', the first occurrence
        public readonly ConflictSide $a,            // the earlier of the two
        public readonly ConflictSide $b,
        public readonly int $occurrences = 1,
    ) {}

    /**
     * The same conflict read from the other side — used when a specific record
     * asks "what clashes with me?" and expects itself first.
     */
    public function flipped(): self
    {
        return new self(
            type: $this->type,
            severity: $this->severity,
            title: $this->title,
            shared: $this->shared,
            date: $this->date,
            a: $this->b,
            b: $this->a,
            occurrences: $this->occurrences,
        );
    }

    public function withOccurrences(int $occurrences): self
    {
        return new self(
            type: $this->type,
            severity: $this->severity,
            title: $this->title,
            shared: $this->shared,
            date: $this->date,
            a: $this->a,
            b: $this->b,
            occurrences: $occurrences,
        );
    }

    public function involves(string $kind, string $recordId): bool
    {
        return $this->a->matches($kind, $recordId) || $this->b->matches($kind, $recordId);
    }

    public function isHard(): bool
    {
        return $this->severity === ConflictSeverity::Hard;
    }

    /**
     * Identity of the *pattern* rather than the day: the same rental crossing the
     * same therapist's working hours every Tuesday is one thing to look at, not
     * four. Order-independent, so it does not matter which side started earlier.
     */
    public function recurrenceKey(): string
    {
        $ids = [$this->a->recurringId(), $this->b->recurringId()];
        sort($ids);

        return $this->type.'|'.implode('|', $ids);
    }
}
