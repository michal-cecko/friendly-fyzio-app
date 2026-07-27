<?php

namespace App\Support\Suggestions;

use App\Enums\SuggestionGroup;
use App\Support\Reservations\ConflictFinder;

/**
 * A single card on the Návrhy surface — something waiting for a human decision.
 *
 * This is a factory, not a value object: the cards travel as plain arrays (the
 * convention {@see ConflictFinder} set, and what the
 * blades read), and this class exists so the shape is declared exactly once.
 */
final class Suggestion
{
    /**
     * The default call-to-action: every card links to the place it is resolved.
     */
    public const CTA = 'Přejít';

    /**
     * @param  string  $type  Rule identity, e.g. `waitlist_offer_lesson`.
     * @param  string  $tone  danger|warning|success|info — drives the card colour.
     * @param  string  $icon  Heroicon name, e.g. `heroicon-m-user-plus`.
     * @param  string  $url  Where "Přejít" goes: the resource that resolves this.
     * @param  int  $priority  Lower sorts first, across all rules.
     * @param  string|null  $id  Record key for per-record rules; null for aggregates.
     * @param  string|null  $meta  Small grey label on the right (usually a date).
     * @param  string|null  $resolveLabel  Inline action label; null = link only.
     * @param  string  $fingerprint  Facts a dismissal is bound to; '' never changes.
     * @param  bool  $snoozeOnDismiss  Aggregates hide for a week, per-record cards
     *                                 until their fingerprint changes.
     * @param  string  $sortKey  Tie-break inside one priority (ISO date works).
     * @return array{
     *     key: string, type: string, id: string|null, group: string, tone: string,
     *     icon: string, title: string, detail: string, meta: string|null,
     *     url: string, cta: string, resolveLabel: string|null, resolveConfirm: string|null,
     *     dismissLabel: string, fingerprint: string, snoozeOnDismiss: bool,
     *     priority: int, sortKey: string
     * }
     */
    public static function make(
        string $type,
        SuggestionGroup $group,
        string $tone,
        string $icon,
        string $title,
        string $detail,
        string $url,
        int $priority,
        ?string $id = null,
        ?string $meta = null,
        ?string $resolveLabel = null,
        ?string $resolveConfirm = null,
        string $fingerprint = '',
        bool $snoozeOnDismiss = false,
        string $sortKey = '',
    ): array {
        return [
            'key' => $id === null ? $type : $type.':'.$id,
            'type' => $type,
            'id' => $id,
            'group' => $group->value,
            'tone' => $tone,
            'icon' => $icon,
            'title' => $title,
            'detail' => $detail,
            'meta' => $meta,
            'url' => $url,
            'cta' => self::CTA,
            'resolveLabel' => $resolveLabel,
            'resolveConfirm' => $resolveConfirm,
            'dismissLabel' => $snoozeOnDismiss ? 'Skrýt na týden' : 'Skrýt',
            'fingerprint' => $fingerprint,
            'snoozeOnDismiss' => $snoozeOnDismiss,
            'priority' => $priority,
            'sortKey' => $sortKey,
        ];
    }
}
