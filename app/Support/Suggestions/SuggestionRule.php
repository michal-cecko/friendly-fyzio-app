<?php

namespace App\Support\Suggestions;

/**
 * One kind of "waiting for a human decision". A rule owns its query, its Czech
 * copy and — when the resolution is a single unambiguous call — the action that
 * carries it out.
 *
 * Rules come in two shapes:
 *
 *   per-record — one card per record, because the record identity is what makes
 *                the card actionable (a specific série with a free spot).
 *   aggregate  — one card carrying a count, linking to a pre-filtered list.
 *                Used wherever the backlog is unbounded, so nothing is ever
 *                silently truncated.
 */
interface SuggestionRule
{
    /**
     * The rule's identity, matching the `type` of every card it emits.
     */
    public function type(): string;

    /**
     * Feature/settings gate. A disabled rule is never queried at all — used
     * where acting on the card would be a dead end anyway.
     */
    public function isEnabled(): bool;

    /**
     * How many cards this rule contributes. Must always equal
     * `count($this->items($cap))`, so the "Zobrazit všech N návrhů" count and
     * the list can never drift apart.
     */
    public function count(int $cap): int;

    /**
     * @return list<array<string, mixed>> at most $cap cards, see {@see Suggestion::make()}
     */
    public function items(int $cap): array;

    /**
     * Carry out the inline resolution and return the Czech toast text.
     *
     * @param  string|null  $id  the record key from the card, null for aggregates
     *
     * @throws \LogicException when the rule offers no inline resolution
     */
    public function resolve(?string $id): string;
}
