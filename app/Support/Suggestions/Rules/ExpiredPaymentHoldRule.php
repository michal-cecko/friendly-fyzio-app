<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\PaymentStatus;
use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Support\Enrollments\ExpiredPaymentHold;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use App\Support\Suggestions\SuggestionRule;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Sign-ups sitting on a spot they never paid for, past the hold window.
 *
 * `enrollments:cancel-unpaid` would have swept these, but the schedule is off
 * until launch — so the backlog is real and someone has to decide per sign-up
 * whether to cancel it or give the client more time. Two cards, because courses
 * and single lessons are two different lists to work through.
 */
class ExpiredPaymentHoldRule implements SuggestionRule
{
    public function type(): string
    {
        return 'expired_payment_hold';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function count(int $cap): int
    {
        return min($cap, count($this->cards(countRows: false)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $cap): array
    {
        return array_slice($this->cards(countRows: true), 0, max(0, $cap));
    }

    public function resolve(?string $id): string
    {
        throw new LogicException('Přihlášky po lhůtě je potřeba projít jednu po druhé.');
    }

    /**
     * @param  bool  $countRows  false only counts whether each list has anything
     *                           in it (the badge path never needs the figure).
     * @return list<array<string, mixed>>
     */
    private function cards(bool $countRows): array
    {
        $scope = StaffScope::current();

        $enrollments = ExpiredPaymentHold::enrollments()
            ->when($scope->userId, fn (Builder $query, string $id) => $query
                ->whereHas('series.course', fn (Builder $course) => $course->where('instructor_id', $id)));

        $bookings = ExpiredPaymentHold::bookings()
            ->when($scope->userId, fn (Builder $query, string $id) => $query
                ->whereHas('lesson', fn (Builder $lesson) => $lesson->where('instructor_id', $id)));

        $cards = [];

        if ($countRows ? ($count = $enrollments->count()) > 0 : $enrollments->exists()) {
            $cards[] = Suggestion::make(
                type: $this->type(),
                group: SuggestionGroup::Kurzy,
                tone: 'danger',
                icon: 'heroicon-m-clock',
                title: 'Přihlášky do kurzů po lhůtě',
                detail: 'Nezaplacených přihlášek po splatnosti: '.($countRows ? $count : '?').'. Zrušte je, nebo lhůtu prodlužte.',
                url: CourseEnrollmentResource::getUrl('index', [
                    'filters' => ['payment_status' => ['value' => PaymentStatus::Unpaid->value]],
                ]),
                priority: 40,
                id: 'enrollments',
                snoozeOnDismiss: true,
            );
        }

        if ($countRows ? ($count = $bookings->count()) > 0 : $bookings->exists()) {
            $cards[] = Suggestion::make(
                type: $this->type(),
                group: SuggestionGroup::Kurzy,
                tone: 'danger',
                icon: 'heroicon-m-clock',
                title: 'Přihlášky na lekce po lhůtě',
                detail: 'Nezaplacených přihlášek na jednotlivé lekce po splatnosti: '.($countRows ? $count : '?').'. Zrušte je, nebo lhůtu prodlužte.',
                url: LessonBookingResource::getUrl('index', [
                    'filters' => ['payment_status' => ['value' => PaymentStatus::Unpaid->value]],
                ]),
                priority: 40,
                id: 'bookings',
                snoozeOnDismiss: true,
            );
        }

        return $cards;
    }
}
