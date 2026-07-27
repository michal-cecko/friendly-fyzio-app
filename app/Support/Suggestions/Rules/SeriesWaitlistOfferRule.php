<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\CourseSeriesStatus;
use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Support\RelationManagers\WaitlistEntriesRelationManager;
use App\Models\CourseSeries;
use App\Support\Enrollments\InviteWaitlistToSpot;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use App\Support\Suggestions\SuggestionRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * A course série has a free spot and people are waiting for it.
 *
 * The SQL form of {@see WaitlistEntriesRelationManager::canPromoteNow()}. The
 * promotion mode is deliberately not part of the condition: on `Ručně` the
 * system will never act at all, and on the automatic modes an invite round that
 * expired with nobody taking the spot leaves exactly the same silence. Both are
 * a spot standing empty with a queue next to it, which is the thing worth
 * saying out loud. A round still running is not — hence
 * `withoutActiveWaitlistInvite()`.
 */
class SeriesWaitlistOfferRule implements SuggestionRule
{
    public function __construct(protected InviteWaitlistToSpot $inviter) {}

    public function type(): string
    {
        return 'waitlist_offer_series';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function count(int $cap): int
    {
        return min($cap, $this->query()->count());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $cap): array
    {
        if ($cap < 1) {
            return [];
        }

        return $this->query()
            // withTakenSpots() feeds spotsLeft() from an eager count; without it
            // every card would fire its own COUNT.
            ->withTakenSpots()
            ->withCount(['waitlistEntries as pending_waitlist_count' => fn (Builder $entries) => $entries->whereNull('notified_at')])
            ->with('course')
            ->orderBy('start_date')
            ->limit($cap)
            ->get()
            ->map(fn (CourseSeries $series): array => $this->card($series))
            ->all();
    }

    public function resolve(?string $id): string
    {
        $series = CourseSeries::query()->findOrFail($id);

        $invited = $this->inviter->handle($series);

        return $invited > 0
            ? "Osloveno čekajících: {$invited}."
            : 'Nikoho nebylo koho oslovit — mezitím se situace změnila.';
    }

    /**
     * @return Builder<CourseSeries>
     */
    private function query(): Builder
    {
        $scope = StaffScope::current();

        return CourseSeries::query()
            ->whereDate('end_date', '>=', today())
            ->whereNot('status', CourseSeriesStatus::Inactive)
            ->hasSpotsLeft()
            ->withoutActiveWaitlistInvite()
            // Not ->pending(): that scope adds an ORDER BY, which is dead weight
            // inside an EXISTS subquery.
            ->whereHas('waitlistEntries', fn (Builder $entries) => $entries->whereNull('notified_at'))
            ->when($scope->userId, fn (Builder $query, string $id) => $query
                ->whereHas('course', fn (Builder $course) => $course->where('instructor_id', $id)));
    }

    /**
     * @return array<string, mixed>
     */
    private function card(CourseSeries $series): array
    {
        $free = $series->spotsLeft();
        $waiting = (int) $series->getAttribute('pending_waitlist_count');
        $name = trim(($series->course?->name ?? 'Kurz').' · '.$series->name, ' ·');

        return Suggestion::make(
            type: $this->type(),
            group: SuggestionGroup::Kurzy,
            tone: 'warning',
            icon: 'heroicon-m-user-plus',
            title: 'Volné místo pro čekající',
            detail: "{$name} — volných míst: {$free}, na čekací listině: {$waiting}.",
            url: CourseSeriesResource::getUrl('view', [
                'record' => $series,
                'relation' => array_search(WaitlistEntriesRelationManager::class, CourseSeriesResource::getRelations(), true),
            ]),
            priority: 30,
            id: $series->getKey(),
            meta: 'od '.$series->start_date?->format('d.m.'),
            resolveLabel: 'Oslovit čekající',
            resolveConfirm: 'Všem čekajícím přijde e-mail s nabídkou volného místa. Kdo se ozve první, místo dostane.',
            fingerprint: "free:{$free}|waiting:{$waiting}",
            sortKey: (string) $series->start_date?->toDateString(),
        );
    }
}
