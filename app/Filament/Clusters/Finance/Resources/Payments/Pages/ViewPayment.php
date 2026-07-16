<?php

namespace App\Filament\Clusters\Finance\Resources\Payments\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\GenerateCashReceiptAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceAction;
use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Support\Payments\PaymentNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markPaid')
                ->label('Označit jako zaplacené')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->modalHeading('Označit platbu jako zaplacenou')
                ->modalSubmitActionLabel('Označit')
                ->visible(fn (Payment $record): bool => $record->status !== PaymentStatus::Paid)
                ->schema([
                    Toggle::make('notify_client')
                        ->label('Poslat potvrzení klientovi')
                        ->default(true),
                ])
                ->action(function (Payment $record, array $data): void {
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
            GenerateInvoiceAction::make(),
            GenerateCashReceiptAction::make(),
        ];
    }
}
