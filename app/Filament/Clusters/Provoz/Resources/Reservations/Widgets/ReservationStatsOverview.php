<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Support\Reservations\ReservationMetrics;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReservationStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    /**
     * Wide, low cards over a couple of rows rather than seven narrow columns:
     * four per row on desktop keeps each card longer than it is tall, which
     * reads far better than cramming them all onto one line.
     */
    protected array|int|null $columns = [
        'default' => 1,
        'sm' => 2,
        'lg' => 3,
        'xl' => 4,
    ];

    protected function getTablePage(): string
    {
        return ListReservations::class;
    }

    protected function getStats(): array
    {
        // Filtered + searched query (pagination not applied) — so the numbers
        // track whatever filters/search the user has active on the table.
        $query = $this->getPageTableQuery();

        $status = ReservationMetrics::statusCounts($query);
        $outstanding = ReservationMetrics::outstanding($query);
        $showMoney = auth()->user()?->canViewRevenue() ?? false;

        // Labels are deliberately kept to a similar length (9–14 characters) so
        // the cards line up as a grid instead of ragged blocks of text.
        return array_values(array_filter([
            $this->stat('Nepotvrzeno', $status[ReservationStatus::Pending->value])
                ->color('warning')
                ->url($this->filterUrl(['status' => ['value' => ReservationStatus::Pending->value]])),
            $this->stat('Potvrzeno', $status[ReservationStatus::Confirmed->value])
                ->color('success')
                ->url($this->filterUrl(['status' => ['value' => ReservationStatus::Confirmed->value]])),
            $this->stat('Stornováno', $status[ReservationStatus::Cancelled->value])
                ->color('danger')
                ->url($this->filterUrl(['status' => ['value' => ReservationStatus::Cancelled->value]])),

            // Money totals stay behind the Revenue capability.
            $showMoney
                ? $this->stat('Tržby celkem', $this->formatCzk(ReservationMetrics::revenue($query)))
                    ->color('success')
                    ->url($this->filterUrl(['payment_status' => ['value' => PaymentStatus::Paid->value]]))
                : null,

            $this->stat(
                'Nezaplaceno',
                $outstanding['count'],
                $showMoney ? $this->formatCzk($outstanding['amount']).' k úhradě' : null,
            )
                ->color($outstanding['count'] > 0 ? 'danger' : 'gray')
                ->url($this->filterUrl(['outstanding' => ['isActive' => true]])),

            $this->stat('Čeká na lékaře', ReservationMetrics::doctorNotePending($query))
                ->color('warning')
                ->url($this->filterUrl(['doctor_note_pending' => ['isActive' => true]])),

            $this->stat('Nevybaveno', ReservationMetrics::unsettledPast($query))
                ->color('gray')
                ->url($this->filterUrl(['unsettled_past' => ['isActive' => true]])),
        ]));
    }

    /**
     * A stat whose description doubles as the plain-text "Zobrazit" affordance —
     * the whole card links to the table with the matching filter applied.
     */
    private function stat(string $label, int|string $value, ?string $description = null): Stat
    {
        return Stat::make($label, $value)
            ->description($description ?? 'Zobrazit')
            ->descriptionIcon('heroicon-m-arrow-right')
            ->extraAttributes(['class' => 'fyz-stat-compact']);
    }

    /**
     * Link to the reservations list with the given filter state applied.
     *
     * @param  array<string, array<string, mixed>>  $filters
     */
    private function filterUrl(array $filters): string
    {
        return ReservationResource::getUrl('index', ['filters' => $filters]);
    }

    private function formatCzk(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Kč';
    }
}
