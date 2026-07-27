<?php

namespace App\Filament\Clusters\Finance\Resources\Payments\Tables;

use App\Contracts\Payable;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\GenerateCashReceiptAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceAction;
use App\Filament\Support\PayableLinks;
use App\Models\Payment;
use App\Support\Invoices\PayableTitle;
use App\Support\Payments\PastDue;
use App\Support\Payments\PaymentNotifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->label('Za co')
                    ->state(fn (Payment $record): string => $record->payable instanceof Payable
                        ? PayableTitle::render($record->payable)['title']
                        : ($record->payable_label ?? '—'))
                    ->url(fn (Payment $record): ?string => PayableLinks::url($record->payable)),
                TextColumn::make('invoice.invoice_number')
                    ->label('Faktura')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label('Částka')
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' Kč')
                    ->sortable(),
                TextColumn::make('method')
                    ->label('Způsob')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('due_at')
                    ->label('Splatnost')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
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
                SelectFilter::make('method')
                    ->label('Způsob platby')
                    ->options(PaymentMethod::class),
                Filter::make('past_due')
                    ->label('Po splatnosti')
                    ->query(fn (Builder $query): Builder => PastDue::payments($query))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->modalHeading(fn (Payment $record): string => 'Upravit platbu č. '.$record->number),
                DeleteAction::make()
                    ->modalHeading(fn (Payment $record): string => 'Smazat platbu č. '.$record->number)
                    ->modalDescription('Platbu tím nevratně odstraníte a související záznam se přepočítá jako neuhrazený. Vystavený pokladní doklad ani faktura nezmizí — jen se od platby odpojí.'),
                GenerateInvoiceAction::make(),
                GenerateCashReceiptAction::make(),
                Action::make('markPaid')
                    ->label('Označit jako zaplacené')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->modalHeading('Označit platbu jako zaplacenou')
                    ->modalSubmitActionLabel('Označit')
                    ->visible(fn (Payment $record): bool => $record->status->isOpen())
                    ->schema([
                        Toggle::make('notify_client')
                            ->label('Poslat potvrzení klientovi')
                            ->default(true),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        // The PaymentObserver settles the payable and issues the
                        // PPD for cash; only the notification stays explicit here.
                        $record->update([
                            'status' => PaymentStatus::Paid,
                            'paid_at' => now(),
                        ]);

                        PaymentNotifier::paymentReceived($record, (bool) ($data['notify_client'] ?? false));

                        Notification::make()
                            ->title('Platba byla označena jako zaplacená.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
