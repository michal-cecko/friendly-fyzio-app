<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Models\Lesson;
use App\Support\Enrollments\InviteWaitlistToSpot;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use App\Support\Suggestions\SuggestionRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * The lesson twin of {@see SeriesWaitlistOfferRule}: a single lesson — a
 * standalone akce, or a lesson of a série sold on its own — with a free place
 * and a queue.
 *
 * A lesson counts its occupancy from attendances, not bookings, and borrows the
 * série's capacity when it has none of its own, so the cards must be built from
 * a `withOccupancyCounts()` query: the `hasSpotsLeft()` scope is raw SQL and
 * populates nothing.
 */
class LessonWaitlistOfferRule implements SuggestionRule
{
    public function __construct(protected InviteWaitlistToSpot $inviter) {}

    public function type(): string
    {
        return 'waitlist_offer_lesson';
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
            ->withOccupancyCounts()
            ->withCount(['waitlistEntries as pending_waitlist_count' => fn (Builder $entries) => $entries->whereNull('notified_at')])
            ->with(['series.course', 'category'])
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->limit($cap)
            ->get()
            ->map(fn (Lesson $lesson): array => $this->card($lesson))
            ->all();
    }

    public function resolve(?string $id): string
    {
        $lesson = Lesson::query()->findOrFail($id);

        $invited = $this->inviter->handle($lesson);

        return $invited > 0
            ? "Osloveno čekajících: {$invited}."
            : 'Nikoho nebylo koho oslovit — mezitím se situace změnila.';
    }

    /**
     * @return Builder<Lesson>
     */
    private function query(): Builder
    {
        $scope = StaffScope::current();

        return Lesson::query()
            ->upcoming()
            ->hasSpotsLeft()
            ->withoutActiveWaitlistInvite()
            ->whereHas('waitlistEntries', fn (Builder $entries) => $entries->whereNull('notified_at'))
            ->when($scope->userId, fn (Builder $query, string $id) => $query->where('instructor_id', $id));
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Lesson $lesson): array
    {
        $free = $lesson->spotsLeft();
        $waiting = (int) $lesson->getAttribute('pending_waitlist_count');
        $when = $lesson->lesson_date->format('d.m.').' '.substr((string) $lesson->start_time, 0, 5);

        return Suggestion::make(
            type: $this->type(),
            group: SuggestionGroup::Kurzy,
            tone: 'warning',
            icon: 'heroicon-m-user-plus',
            title: 'Volné místo pro čekající',
            detail: "{$lesson->displayName()} · {$when} — volných míst: {$free}, na čekací listině: {$waiting}.",
            url: LessonResource::getUrl('view', ['record' => $lesson]),
            priority: 30,
            id: $lesson->getKey(),
            meta: $lesson->lesson_date->format('d.m.'),
            resolveLabel: 'Oslovit čekající',
            resolveConfirm: 'Všem čekajícím přijde e-mail s nabídkou volného místa. Kdo se ozve první, místo dostane.',
            fingerprint: "free:{$free}|waiting:{$waiting}",
            sortKey: $lesson->lesson_date->toDateString().' '.$lesson->start_time,
        );
    }
}
