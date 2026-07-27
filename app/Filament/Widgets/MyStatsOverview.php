<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Pages\Calendar;
use App\Filament\Widgets\Concerns\OwnWork;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Support\CalendarAvailability;
use App\Support\Reservations\ReservationMetrics;
use App\Support\Reservations\Slot;
use Carbon\CarbonPeriod;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The viewer's own numbers, the staff counterpart to {@see AdminStatsOverview}.
 *
 * Composed of two blocks that each appear only with the capability behind them:
 * a therapist gets their day, week, month and what is still open on their past
 * visits; a lecturer gets their teaching week. Someone holding both sees both,
 * which is why this is one widget rather than two — the cards then flow as a
 * single grid instead of two ragged rows.
 *
 * Every figure is built from the same two scoped builders the "my" table widgets
 * use, so a number can never disagree with the list it links to.
 */
class MyStatsOverview extends StatsOverviewWidget
{
    use OwnWork;

    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    /**
     * Three across rather than Filament's four: with both blocks present there
     * are six cards, which then read as two even rows.
     */
    protected array|int|null $columns = ['default' => 1, 'sm' => 2, 'lg' => 3];

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $user = $this->viewer();

        return array_values(array_filter([
            ...($user?->isTherapist() ? $this->therapistStats() : []),
            ...($user?->isLecturer() ? $this->lecturerStats() : []),
        ]));
    }

    /**
     * @return array<int, Stat>
     */
    private function therapistStats(): array
    {
        $today = $this->ownReservations()
            ->whereDate('reservation_date', today())
            ->whereNot('status', ReservationStatus::Cancelled)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        $next = $today->first(fn (Reservation $reservation): bool => $reservation->start_time >= now()->format('H:i:s'));

        [$utilization, $bookedHours, $availableHours] = $this->weekUtilization();

        $month = $this->ownReservations()
            ->where('status', ReservationStatus::Confirmed)
            ->whereBetween('reservation_date', [now()->startOfMonth()->toDateString(), today()->toDateString()])
            ->get(['start_time', 'end_time']);

        $unsettled = ReservationMetrics::unsettledPast($this->ownReservations());
        $outstanding = ReservationMetrics::outstanding($this->ownReservations());
        $showMoney = auth()->user()?->canViewRevenue() ?? false;

        return [
            Stat::make('Dnes mám', $today->count())
                ->description($next !== null
                    ? 'Další v '.substr((string) $next->start_time, 0, 5)
                    : ($today->isEmpty() ? 'Volný den' : 'Program hotový'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->url(Calendar::getUrl())
                ->extraAttributes(['class' => 'fyz-stat-compact']),

            Stat::make('Můj týden', $utilization === null ? '—' : "{$utilization} %")
                ->description($utilization === null
                    ? 'Bez pracovní doby'
                    : "{$bookedHours} z {$availableHours} h")
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color(match (true) {
                    $utilization === null => 'gray',
                    $utilization >= 70 => 'success',
                    $utilization >= 40 => 'warning',
                    default => 'danger',
                })
                ->extraAttributes(['class' => 'fyz-stat-compact']),

            Stat::make('Tento měsíc', $month->count())
                ->description($this->hours($month).' h odpracováno')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray')
                ->extraAttributes(['class' => 'fyz-stat-compact']),

            Stat::make('Nevybaveno', $unsettled)
                ->description('Uzavřít návštěvy')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color($unsettled > 0 ? 'warning' : 'gray')
                ->url($this->reservationFilterUrl(['unsettled_past' => ['isActive' => true]]))
                ->extraAttributes(['class' => 'fyz-stat-compact']),

            Stat::make('Nezaplaceno', $outstanding['count'])
                ->description($showMoney
                    ? $this->formatCzk($outstanding['amount']).' k úhradě'
                    : 'Zobrazit')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color($outstanding['count'] > 0 ? 'danger' : 'gray')
                ->url($this->reservationFilterUrl(['outstanding' => ['isActive' => true]]))
                ->extraAttributes(['class' => 'fyz-stat-compact']),
        ];
    }

    /**
     * @return array<int, Stat>
     */
    private function lecturerStats(): array
    {
        $week = $this->ownLessons()
            ->whereBetween('lesson_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->withOccupancyCounts()
            ->get();

        $next = $this->ownLessons()
            ->upcoming()
            ->with(['series', 'category'])
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->first();

        $unpaidSignups = CourseEnrollment::query()
            ->where('payment_status', PaymentStatus::Unpaid)
            ->whereHas('series.course', fn (Builder $course): Builder => $course->where('instructor_id', $this->ownUserId()))
            ->count();

        return [
            Stat::make('Lekce tento týden', $week->count())
                ->description($week->sum('present_count').' přihlášených')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary')
                ->extraAttributes(['class' => 'fyz-stat-compact']),

            Stat::make(
                'Nejbližší lekce',
                $next === null
                    ? '—'
                    : $next->lesson_date->format('d.m.').' '.substr((string) $next->start_time, 0, 5),
            )
                ->description($next === null
                    ? 'Nic naplánováno'
                    : ($next->series?->name ?? $next->category?->name ?? 'Samostatná akce'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($next === null ? 'gray' : 'success')
                ->url($next === null ? null : LessonResource::getUrl('view', ['record' => $next]))
                ->extraAttributes(['class' => 'fyz-stat-compact']),

            Stat::make('Nezaplacené přihlášky', $unpaidSignups)
                ->description('Zobrazit')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color($unpaidSignups > 0 ? 'warning' : 'gray')
                ->url(CourseEnrollmentResource::getUrl('index', [
                    'filters' => ['payment_status' => ['value' => PaymentStatus::Unpaid->value]],
                ]))
                ->extraAttributes(['class' => 'fyz-stat-compact']),
        ];
    }

    /**
     * Reservations the viewer runs. Also the base for the "my" reservation table,
     * so the stats and that list can never diverge.
     *
     * @return Builder<Reservation>
     */
    private function ownReservations(): Builder
    {
        return Reservation::query()->where('therapist_id', $this->ownStaffProfileId());
    }

    /**
     * @return Builder<Lesson>
     */
    private function ownLessons(): Builder
    {
        return Lesson::query()->where('instructor_id', $this->ownUserId());
    }

    /**
     * Booked ÷ available working time this week, for this therapist alone.
     *
     * @return array{0: int|null, 1: int, 2: int} [percent|null, bookedHours, availableHours]
     */
    private function weekUtilization(): array
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();
        $profileId = $this->ownStaffProfileId();

        if ($profileId === null) {
            return [null, 0, 0];
        }

        $availability = app(CalendarAvailability::class);
        $availableMinutes = 0;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $availableMinutes += $availability->availableMinutes($day, [$profileId]);
        }

        $bookedMinutes = $this->bookedMinutes(
            $this->ownReservations()
                ->whereBetween('reservation_date', [$start->toDateString(), $end->toDateString()])
                ->whereNot('status', ReservationStatus::Cancelled)
                ->get(['start_time', 'end_time']),
        );

        $percent = $availableMinutes > 0
            ? (int) round($bookedMinutes / $availableMinutes * 100)
            : null;

        return [$percent, (int) round($bookedMinutes / 60), (int) round($availableMinutes / 60)];
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function hours(Collection $reservations): int
    {
        return (int) round($this->bookedMinutes($reservations) / 60);
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function bookedMinutes(Collection $reservations): int
    {
        return (int) $reservations->sum(fn (Reservation $reservation): int => max(
            0,
            Slot::toMinutes($reservation->end_time) - Slot::toMinutes($reservation->start_time),
        ));
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     */
    private function reservationFilterUrl(array $filters): string
    {
        return ReservationResource::getUrl('index', ['filters' => $filters]);
    }

    private function formatCzk(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Kč';
    }
}
