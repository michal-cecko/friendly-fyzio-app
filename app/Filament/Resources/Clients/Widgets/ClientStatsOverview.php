<?php

namespace App\Filament\Resources\Clients\Widgets;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ClientStatsOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        if (! $this->record instanceof User) {
            return [];
        }

        $client = $this->record;

        $totalReservations = $client->reservations()->count();
        $cancelledReservations = $client->reservations()
            ->where('status', ReservationStatus::Cancelled)
            ->count();

        $lastReservationDate = $client->reservations()
            ->whereDate('reservation_date', '<=', today())
            ->max('reservation_date');
        $nextReservationDate = $client->reservations()
            ->whereDate('reservation_date', '>', today())
            ->whereNot('status', ReservationStatus::Cancelled)
            ->min('reservation_date');

        $creditBalance = $client->creditAccount?->balance ?? 0;

        $totalSpent = Payment::query()
            ->where('client_id', $client->getKey())
            ->whereNotNull('paid_at')
            ->sum('amount');

        $activeEnrollments = $client->courseEnrollments()
            ->where('status', CourseEnrollmentStatus::Active)
            ->count();
        $totalEnrollments = $client->courseEnrollments()->count();

        return [
            Stat::make('Rezervace', $totalReservations)
                ->description("{$cancelledReservations} zrušených")
                ->color('primary'),
            Stat::make('Poslední rezervace', $lastReservationDate
                ? Carbon::parse($lastReservationDate)->format('d.m.Y')
                : '—')
                ->description($nextReservationDate
                    ? 'Další: '.Carbon::parse($nextReservationDate)->format('d.m.Y')
                    : 'Žádná nadcházející')
                ->descriptionIcon('heroicon-m-calendar-days'),
            Stat::make('Kredit', number_format($creditBalance, 0, ',', ' ').' Kč')
                ->color('success'),
            Stat::make('Utraceno', number_format($totalSpent, 0, ',', ' ').' Kč'),
            Stat::make('Kurzy', "{$activeEnrollments} aktivních")
                ->description("{$totalEnrollments} celkem"),
        ];
    }
}
