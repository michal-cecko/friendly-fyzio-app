<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AdminOnly;
use App\Models\Payment;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class RevenueChartWidget extends ApexChartWidget
{
    use AdminOnly;

    protected static ?string $chartId = 'revenueChart';

    protected static ?string $heading = 'Tržby (Kč)';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = 'tyden';

    /**
     * Revenue is recognised on `paid_at` (the app convention), split by what the
     * payment was for via the payable morph alias.
     */
    private const SERIES_LABELS = [
        'reservation' => 'Terapie',
        'course_enrollment' => 'Kurzy',
        'one_off_event_booking' => 'Jednorázové akce',
    ];

    protected function getFilters(): ?array
    {
        return [
            'tyden' => 'Tento týden',
            'mesic' => 'Tento měsíc',
        ];
    }

    protected function getOptions(): array
    {
        [$from, $to] = $this->filter === 'mesic'
            ? [now()->startOfMonth(), now()->endOfMonth()]
            : [now()->startOfWeek(), now()->endOfWeek()];

        $days = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(fn (Carbon $day): string => $day->toDateString())
            ->values();

        // [seriesLabel => [dateKey => sum]], seeded to zero so every day shows.
        $sums = [];
        foreach ([...array_values(self::SERIES_LABELS), 'Ostatní'] as $label) {
            $sums[$label] = array_fill_keys($days->all(), 0);
        }

        Payment::query()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->get(['amount', 'paid_at', 'payable_type'])
            ->each(function (Payment $payment) use (&$sums): void {
                $label = self::SERIES_LABELS[$payment->payable_type] ?? 'Ostatní';
                $day = $payment->paid_at->toDateString();

                if (isset($sums[$label][$day])) {
                    $sums[$label][$day] += $payment->amount;
                }
            });

        // Always show the three offering types; add "Ostatní" only when it has data.
        $series = [];
        foreach ($sums as $label => $byDay) {
            if ($label === 'Ostatní' && array_sum($byDay) === 0) {
                continue;
            }
            $series[] = ['name' => $label, 'data' => array_values($byDay)];
        }

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 300,
                'stacked' => true,
                'toolbar' => ['show' => false],
                'fontFamily' => 'inherit',
            ],
            'series' => $series,
            'xaxis' => [
                'categories' => $days->map(fn (string $day): string => Carbon::parse($day)->format('d.m.'))->all(),
                'labels' => ['style' => ['fontFamily' => 'inherit']],
            ],
            'yaxis' => ['labels' => ['style' => ['fontFamily' => 'inherit']]],
            'colors' => ['#d4678a', '#6366f1', '#f59e0b', '#10b981', '#9ca3af'],
            'plotOptions' => ['bar' => ['borderRadius' => 3, 'columnWidth' => '60%']],
            'dataLabels' => ['enabled' => false],
            'legend' => ['position' => 'top', 'fontFamily' => 'inherit'],
        ];
    }
}
