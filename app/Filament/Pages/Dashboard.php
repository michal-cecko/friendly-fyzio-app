<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Widgets\ReservationCalendar;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }

    /**
     * The reservation calendar lives on its own dedicated page, so keep it out
     * of the dashboard grid. Admins additionally drop the generic Account/Info
     * widgets for a clean clinic overview; pure therapists keep them until their
     * own dashboard ships.
     *
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $hidden = [ReservationCalendar::class];

        if (auth()->user()?->isAdmin()) {
            $hidden[] = AccountWidget::class;
            $hidden[] = FilamentInfoWidget::class;
        }

        return array_values(array_filter(
            parent::getWidgets(),
            fn (string|WidgetConfiguration $widget): bool => ! in_array($this->normalizeWidgetClass($widget), $hidden, true),
        ));
    }

    /**
     * Quick actions — admins only.
     *
     * @return array<Action | ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        if (! auth()->user()?->isAdmin()) {
            return [];
        }

        return [
            Action::make('newReservation')
                ->label('Nová rezervace')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->url(Calendar::getUrl()),
            Action::make('addClient')
                ->label('Přidat klienta')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->url(ClientResource::getUrl('create')),
            ActionGroup::make([
                Action::make('blockCalendar')
                    ->label('Blokovat kalendář')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->url(Calendar::getUrl()),
                Action::make('newInvoice')
                    ->label('Vystavit fakturu')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(InvoiceResource::getUrl('create')),
            ])
                ->label('Další akce')
                ->button()
                ->color('gray'),
        ];
    }
}
