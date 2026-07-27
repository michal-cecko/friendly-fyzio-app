<?php

namespace App\Filament\Clusters\Finance\Resources\Payments\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\GenerateCashReceiptAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceAction;
use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Payment;
use App\Support\Payments\PaymentNotifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        /** @var Payment $record */
        $record = $this->getRecord();

        return 'Platba č. '.$record->number.' — '.($record->client?->name ?? 'Neznámý klient');
    }

    protected function getHeaderActions(): array
    {
        return [
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
            EditAction::make()
                ->modalHeading(fn (Payment $record): string => 'Upravit platbu č. '.$record->number),
            GenerateInvoiceAction::make(),
            GenerateCashReceiptAction::make(),
            DeleteAction::make()
                ->modalHeading(fn (Payment $record): string => 'Smazat platbu č. '.$record->number)
                ->modalDescription('Platbu tím nevratně odstraníte a související záznam se přepočítá jako neuhrazený. Vystavený pokladní doklad ani faktura nezmizí — jen se od platby odpojí.'),
            ActivityLogAction::make(),
        ];
    }
}
