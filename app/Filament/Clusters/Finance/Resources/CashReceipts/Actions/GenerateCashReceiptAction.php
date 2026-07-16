<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Actions;

use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Support\Invoices\CashReceiptGenerator;
use App\Support\Invoices\DocumentNumberAllocator;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Issues a příjmový pokladní doklad. Dual-placed: on a received cash Payment
 * (fromPayment) and on a cash Invoice without a receipt (fromInvoice).
 */
class GenerateCashReceiptAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateCashReceipt';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Vystavit příjmový doklad')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('gray')
            ->modalHeading('Vystavit příjmový pokladní doklad')
            ->modalIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->modalSubmitActionLabel('Vystavit')
            ->visible(fn (Model $record): bool => match (true) {
                $record instanceof Payment => $record->method === PaymentMethod::Cash
                    && $record->status === PaymentStatus::Paid
                    && ! $record->cashReceipt()->exists(),
                $record instanceof Invoice => $record->payment_method === PaymentMethod::Cash
                    && ! $record->cashReceipt()->exists(),
                default => false,
            })
            ->schema([
                Select::make('series_id')
                    ->label('Číselná řada')
                    ->options(fn () => InvoiceSeries::query()
                        ->where('document_type', DocumentType::Receipt->value)
                        ->pluck('name', 'id'))
                    ->default(fn (): ?string => app(DocumentNumberAllocator::class)
                        ->defaultSeries(DocumentType::Receipt)?->getKey())
                    ->required()
                    ->native(false),
            ])
            ->action(function (Model $record, array $data): void {
                $series = InvoiceSeries::query()->findOrFail($data['series_id']);
                $generator = app(CashReceiptGenerator::class);

                $receipt = $record instanceof Payment
                    ? $generator->fromPayment($record, $series)
                    : $generator->fromInvoice($record, $series);

                Notification::make()
                    ->title("Pokladní doklad {$receipt->receipt_number} byl vystaven.")
                    ->success()
                    ->send();
            });
    }
}
