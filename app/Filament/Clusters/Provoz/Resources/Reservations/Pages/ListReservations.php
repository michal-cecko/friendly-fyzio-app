<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Pages;

use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Clusters\Provoz\Resources\Reservations\Widgets\ReservationStatsOverview;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListReservations extends ListRecords
{
    /**
     * Required by the stats widget's `InteractsWithPageTable`: it passes the
     * table state (search, filters, tab…) down as reactive props. Without it
     * the widget's typed `array $tableColumnSearches` prop hydrates as `null`
     * on every Livewire update and the stats ignore the active filters.
     */
    use ExposesTableToWidgets;

    protected static string $resource = ReservationResource::class;

    /** Whether the metric cards are shown above the table (per-user preference). */
    public bool $showStats = true;

    public function mount(): void
    {
        parent::mount();

        $this->showStats = (bool) auth()->user()->getPreference('reservations.show_stats', true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleStats')
                ->label(fn (): string => $this->showStats ? 'Skrýt statistiky' : 'Zobrazit statistiky')
                ->icon(fn (): Heroicon => $this->showStats ? Heroicon::EyeSlash : Heroicon::Eye)
                ->color('gray')
                ->action(function (): void {
                    $this->showStats = ! $this->showStats;
                    auth()->user()->setPreference('reservations.show_stats', $this->showStats);
                }),
            CreateAction::make()
                ->schema(ReservationForm::components()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return $this->showStats ? [ReservationStatsOverview::class] : [];
    }
}
