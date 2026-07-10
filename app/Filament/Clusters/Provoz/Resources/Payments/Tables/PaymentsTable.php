<?php

namespace App\Filament\Clusters\Provoz\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('variable_symbol')
                    ->label('VS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payable')
                    ->label('Rezervace')
                    ->state(fn (Payment $record): string => $record->payable instanceof Reservation
                        ? $record->payable->startsAt()->format('d.m.Y H:i')
                        : '—'),
                TextColumn::make('amount')
                    ->label('Částka')
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' Kč')
                    ->sortable(),
                TextColumn::make('method')
                    ->label('Způsob')
                    ->badge()
                    ->formatStateUsing(fn (PaymentMethod $state): string => match ($state) {
                        PaymentMethod::Qr => 'QR platba',
                        PaymentMethod::Cash => 'Hotovost',
                        PaymentMethod::Credit => 'Kredit',
                    }),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Zaplaceno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(PaymentStatus::class),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Označit jako zaplacené')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record): bool => $record->status !== PaymentStatus::Paid)
                    ->action(function (Payment $record): void {
                        $record->update([
                            'status' => PaymentStatus::Paid,
                            'paid_at' => now(),
                        ]);

                        if ($record->payable instanceof Reservation) {
                            $record->payable->update(['payment_status' => PaymentStatus::Paid]);
                        }

                        Notification::make()
                            ->title('Platba byla označena jako zaplacená.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
