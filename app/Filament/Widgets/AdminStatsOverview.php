<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Pages\Calendar;
use App\Filament\Widgets\Concerns\AdminOnly;
use App\Models\Reservation;
use App\Models\User;
use App\Support\CalendarAvailability;
use App\Support\Reservations\Slot;
use Carbon\CarbonPeriod;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    use AdminOnly;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $todayActive = Reservation::query()
            ->whereDate('reservation_date', today())
            ->whereNot('status', ReservationStatus::Cancelled);
        $todayCount = (clone $todayActive)->count();
        $todayPending = (clone $todayActive)->where('status', ReservationStatus::Pending)->count();

        $awaitingConfirmation = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->whereDate('reservation_date', '>=', today())
            ->count();

        $newClients = User::query()
            ->where('role', UserRole::Customer)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        [$utilization, $bookedHours, $availableHours] = $this->weeklyUtilization();

        return [
            Stat::make('Dnešní rezervace', $todayCount)
                ->description($todayPending > 0 ? "{$todayPending} čeká na potvrzení" : 'Vše potvrzeno')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->url(Calendar::getUrl()),
            Stat::make('Čeká na potvrzení', $awaitingConfirmation)
                ->description('Nadcházející rezervace')
                ->descriptionIcon('heroicon-m-clock')
                ->color($awaitingConfirmation > 0 ? 'warning' : 'success')
                ->url(ReservationResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => ReservationStatus::Pending->value]],
                ])),
            Stat::make('Noví klienti', $newClients)
                ->description('Tento týden')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),
            Stat::make('Obsazenost tento týden', $utilization === null ? '—' : "{$utilization} %")
                ->description($utilization === null ? 'Bez pracovní doby' : "{$bookedHours} z {$availableHours} h")
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color(match (true) {
                    $utilization === null => 'gray',
                    $utilization >= 70 => 'success',
                    $utilization >= 40 => 'warning',
                    default => 'danger',
                }),
        ];
    }

    /**
     * Booked ÷ available working time this week.
     *
     * @return array{0: int|null, 1: int, 2: int} [percent|null, bookedHours, availableHours]
     */
    private function weeklyUtilization(): array
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        $availability = app(CalendarAvailability::class);
        $availableMinutes = 0;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $availableMinutes += $availability->availableMinutes($day);
        }

        $bookedMinutes = (int) Reservation::query()
            ->whereBetween('reservation_date', [$start->toDateString(), $end->toDateString()])
            ->whereNot('status', ReservationStatus::Cancelled)
            ->get(['start_time', 'end_time'])
            ->sum(fn (Reservation $r): int => max(0, Slot::toMinutes($r->end_time) - Slot::toMinutes($r->start_time)));

        $percent = $availableMinutes > 0
            ? (int) round($bookedMinutes / $availableMinutes * 100)
            : null;

        return [$percent, (int) round($bookedMinutes / 60), (int) round($availableMinutes / 60)];
    }
}
