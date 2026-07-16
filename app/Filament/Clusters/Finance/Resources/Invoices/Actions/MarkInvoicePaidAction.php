<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Support\Invoices\CashReceiptGenerator;
use App\Support\Payments\PaymentNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Marks the invoice paid and cascades to its linked payments — each via a model
 * update so the PaymentObserver fires (payable settlement + cash PPD). A cash
 * invoice with no payments gets its PPD directly.
 */
class MarkInvoicePaidAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'markInvoicePaid';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Označit jako zaplacenou')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading('Označit fakturu jako zaplacenou')
            ->modalDescription('Označí fakturu i navázané platby jako zaplacené.')
            ->modalSubmitActionLabel('Označit')
            ->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Paid)
            ->schema([
                Toggle::make('notify_client')
                    ->label('Poslat potvrzení klientovi')
                    ->default(true),
            ])
            ->action(function (Invoice $record, array $data): void {
                DB::transaction(function () use ($record): void {
                    $record->update([
                        'status' => InvoiceStatus::Paid,
                        'paid_at' => now(),
                    ]);

                    $record->payments()
                        ->where('status', '!=', PaymentStatus::Paid->value)
                        ->get()
                        ->each
                        ->update([
                            'status' => PaymentStatus::Paid,
                            'paid_at' => now(),
                        ]);

                    if (
                        $record->payment_method === PaymentMethod::Cash
                        && ! $record->cashReceipt()->exists()
                        && ! $record->payments()->exists()
                    ) {
                        app(CashReceiptGenerator::class)->fromInvoice($record);
                    }
                });

                if ($data['notify_client'] ?? false) {
                    $record->payments()
                        ->where('status', PaymentStatus::Paid->value)
                        ->latest('paid_at')
                        ->first()
                        ?->tap(fn ($payment) => PaymentNotifier::paymentReceived($payment));
                }

                Notification::make()
                    ->title('Faktura byla označena jako zaplacená.')
                    ->success()
                    ->send();
            });
    }
}
