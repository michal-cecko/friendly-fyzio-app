<?php

namespace App\Support\Suggestions;

use App\Enums\SuggestionGroup;
use App\Models\SuggestionDismissal;
use App\Support\Reservations\ConflictFinder;
use App\Support\Suggestions\Rules\DayWaitlistNotifiableRule;
use App\Support\Suggestions\Rules\DoctorNotePendingRule;
use App\Support\Suggestions\Rules\DropInPriceMissingRule;
use App\Support\Suggestions\Rules\ExpiredPaymentHoldRule;
use App\Support\Suggestions\Rules\HiddenReviewsRule;
use App\Support\Suggestions\Rules\LessonWaitlistOfferRule;
use App\Support\Suggestions\Rules\MissingVisitNoteRule;
use App\Support\Suggestions\Rules\NewContactInquiriesRule;
use App\Support\Suggestions\Rules\PastDueInvoicesRule;
use App\Support\Suggestions\Rules\PastDuePaymentsRule;
use App\Support\Suggestions\Rules\SeriesWaitlistOfferRule;
use App\Support\Suggestions\Rules\UnsettledPastVisitsRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Once;

/**
 * Everything waiting for a human decision, gathered from every domain — the
 * actionable twin of {@see ConflictFinder}, which
 * reports what is broken rather than what is merely undone.
 *
 * Two paths, deliberately unequal in cost:
 *
 *   count()  runs on every admin page render (the sidebar badge) and never
 *            hydrates a model — exists()/count() only.
 *   all()    runs on the dashboard and the Návrhy page, and pays for the
 *            eager loads each card's copy needs.
 *
 * Both memoise with once(), which Octane flushes per request; a static cache
 * would leak between requests. Actions that change the picture (resolve,
 * dismiss) call {@see flush()} so the same-request re-render is not stale.
 */
final class SuggestionFinder
{
    /**
     * Cards a single rule may contribute, so one backlog cannot flood the page.
     * Rules whose volume is unbounded collapse to one aggregate card instead of
     * being truncated here.
     */
    public const RULE_CAP = 10;

    /**
     * @return list<class-string<SuggestionRule>>
     */
    public static function ruleClasses(): array
    {
        return [
            DayWaitlistNotifiableRule::class,
            SeriesWaitlistOfferRule::class,
            LessonWaitlistOfferRule::class,
            ExpiredPaymentHoldRule::class,
            PastDuePaymentsRule::class,
            PastDueInvoicesRule::class,
            UnsettledPastVisitsRule::class,
            MissingVisitNoteRule::class,
            DoctorNotePendingRule::class,
            NewContactInquiriesRule::class,
            HiddenReviewsRule::class,
            DropInPriceMissingRule::class,
        ];
    }

    /**
     * @return list<SuggestionRule>
     */
    public static function rules(): array
    {
        return array_values(array_filter(
            array_map(fn (string $rule): SuggestionRule => app($rule), self::ruleClasses()),
            fn (SuggestionRule $rule): bool => $rule->isEnabled(),
        ));
    }

    public static function ruleFor(string $type): SuggestionRule
    {
        foreach (self::rules() as $rule) {
            if ($rule->type() === $type) {
                return $rule;
            }
        }

        throw new \InvalidArgumentException("Neznámý typ návrhu: {$type}");
    }

    /**
     * Every open card, most urgent first.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return once(function (): array {
            $dismissed = self::dismissals();

            $suggestions = [];

            foreach (self::rules() as $rule) {
                foreach ($rule->items(self::RULE_CAP) as $suggestion) {
                    if (! self::isDismissed($suggestion, $dismissed)) {
                        $suggestions[] = $suggestion;
                    }
                }
            }

            usort($suggestions, fn (array $a, array $b): int => [$a['priority'], $a['sortKey'], $a['type']]
                <=> [$b['priority'], $b['sortKey'], $b['type']]);

            return $suggestions;
        });
    }

    /**
     * The badge figure. Counts cards, not records — aggregate rules contribute
     * exactly one — so it always equals count(all()).
     */
    public static function count(): int
    {
        return once(function (): int {
            $dismissed = self::dismissals();

            // A dismissed card must not be counted, and only items() knows a
            // card's fingerprint — so any rule with a live dismissal is asked
            // for its cards, while the rest answer with a cheap COUNT.
            $total = 0;

            foreach (self::rules() as $rule) {
                $total += $dismissed->has($rule->type())
                    ? count(array_filter(
                        $rule->items(self::RULE_CAP),
                        fn (array $suggestion): bool => ! self::isDismissed($suggestion, $dismissed),
                    ))
                    : $rule->count(self::RULE_CAP);
            }

            return $total;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function top(int $limit = 5): array
    {
        return array_slice(self::all(), 0, $limit);
    }

    /**
     * Cards keyed by group, in the fixed group order, empty groups dropped.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $byGroup = [];

        foreach (SuggestionGroup::cases() as $group) {
            $cards = array_values(array_filter(
                self::all(),
                fn (array $suggestion): bool => $suggestion['group'] === $group->value,
            ));

            if ($cards !== []) {
                $byGroup[$group->value] = $cards;
            }
        }

        return $byGroup;
    }

    /**
     * Dismissals still in force, grouped by rule type.
     *
     * @return Collection<string, Collection<int, SuggestionDismissal>>
     */
    public static function dismissals(): Collection
    {
        return once(fn (): Collection => SuggestionDismissal::query()
            ->active()
            ->get()
            ->groupBy('type'));
    }

    /**
     * Hidden cards that would otherwise be on the list right now, so the page
     * can offer them back. Ignores dismissals whose card no longer applies.
     *
     * @return list<array{suggestion: array<string, mixed>, dismissal: SuggestionDismissal}>
     */
    public static function hidden(): array
    {
        $dismissed = self::dismissals();

        if ($dismissed->isEmpty()) {
            return [];
        }

        $hidden = [];

        foreach (self::rules() as $rule) {
            if (! $dismissed->has($rule->type())) {
                continue;
            }

            foreach ($rule->items(self::RULE_CAP) as $suggestion) {
                $dismissal = self::dismissalFor($suggestion, $dismissed);

                if ($dismissal !== null) {
                    $hidden[] = ['suggestion' => $suggestion, 'dismissal' => $dismissal];
                }
            }
        }

        return $hidden;
    }

    /**
     * Forgets this request's memoised answers — call after anything that
     * changes the picture, so the re-render shows the new one.
     */
    public static function flush(): void
    {
        Once::flush();
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @param  Collection<string, Collection<int, SuggestionDismissal>>  $dismissed
     */
    private static function isDismissed(array $suggestion, Collection $dismissed): bool
    {
        return self::dismissalFor($suggestion, $dismissed) !== null;
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @param  Collection<string, Collection<int, SuggestionDismissal>>  $dismissed
     */
    private static function dismissalFor(array $suggestion, Collection $dismissed): ?SuggestionDismissal
    {
        return $dismissed->get($suggestion['type'], collect())
            ->first(fn (SuggestionDismissal $dismissal): bool => $dismissal->key === $suggestion['key']
                && $dismissal->fingerprint === $suggestion['fingerprint']);
    }
}
