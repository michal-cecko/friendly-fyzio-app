<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\EventCategory;
use App\Support\Lessons\ReleaseFreeSpots;
use App\Support\Settings;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use App\Support\Suggestions\SuggestionRule;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * The silent dead end of {@see ReleaseFreeSpots}: a course
 * with free places on its upcoming lessons that can never be sold one at a
 * time, because pricing a single lesson is the deliberate act that opts a
 * course into drop-in sales — and nobody has done it.
 *
 * Switched off entirely when the drop-in category is missing, since the release
 * engine would bail on that first and the card would lead nowhere.
 */
class DropInPriceMissingRule implements SuggestionRule
{
    public function type(): string
    {
        return 'drop_in_price_missing';
    }

    public function isEnabled(): bool
    {
        return EventCategory::query()->where('slug', Settings::dropInCategorySlug())->exists();
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
            ->orderBy('name')
            ->limit($cap)
            ->get()
            ->map(fn (Course $course): array => Suggestion::make(
                type: $this->type(),
                group: SuggestionGroup::Kurzy,
                tone: 'info',
                icon: 'heroicon-m-banknotes',
                title: 'Kurz nemá cenu jednotlivé lekce',
                detail: "{$course->name} — volná místa v nadcházejících lekcích by šlo prodat jako jednorázový vstup.",
                url: CourseResource::getUrl('edit', ['record' => $course]),
                priority: 90,
                id: $course->getKey(),
                sortKey: (string) $course->name,
            ))
            ->all();
    }

    public function resolve(?string $id): string
    {
        throw new LogicException('Cenu jednorázového vstupu je potřeba vyplnit v kurzu.');
    }

    /**
     * @return Builder<Course>
     */
    private function query(): Builder
    {
        $scope = StaffScope::current();

        return Course::query()
            ->whereNull('drop_in_price')
            ->whereHas('series.lessons', fn (Builder $lessons) => $lessons
                ->whereDate('lesson_date', '>=', today())
                ->hasSpotsLeft())
            ->when($scope->userId, fn (Builder $query, string $id) => $query->where('instructor_id', $id));
    }
}
