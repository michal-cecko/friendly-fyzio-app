<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Actions;

use App\Contracts\Payable;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Support\Invoices\DocumentNumberAllocator;
use App\Support\Invoices\InvoiceGenerator;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Convenience entry from a payment row — routes to the payable's paid-only
 * invoice; an unlinked payment (its payable was force-deleted) gets a
 * single-line invoice documenting just that received transfer.
 */
class GenerateInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateInvoice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Vystavit fakturu')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('primary')
            ->modalHeading('Vystavit fakturu k platbě')
            ->modalIcon(Heroicon::OutlinedDocumentText)
            ->modalSubmitActionLabel('Vystavit')
            ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Paid
                && $record->invoice_id === null
                && ($record->payable === null
                    || ($record->payable instanceof Payable
                        && $record->payable->hasPaidStatus()
                        && $record->payable->invoice()->doesntExist())))
            ->schema([
                Select::make('series_id')
                    ->label('Číselná řada')
                    ->options(fn () => InvoiceSeries::query()
                        ->where('document_type', DocumentType::Invoice->value)
                        ->pluck('name', 'id'))
                    ->default(fn (): ?string => app(DocumentNumberAllocator::class)
                        ->defaultSeries(DocumentType::Invoice)?->getKey())
                    ->required()
                    ->native(false),
                DatePicker::make('issued_at')
                    ->label('Datum vystavení')
                    ->native(false)
                    ->default(today())
                    ->required(),
                DatePicker::make('due_at')
                    ->label('Splatnost')
                    ->native(false)
                    ->default(fn (): Carbon => today()->addDays(Settings::invoiceDueDays()))
                    ->required(),
            ])
            ->action(function (Payment $record, array $data): void {
                $invoice = app(InvoiceGenerator::class)->fromPayment(
                    $record,
                    InvoiceSeries::query()->findOrFail($data['series_id']),
                    Carbon::parse($data['issued_at']),
                    Carbon::parse($data['due_at']),
                );

                Notification::make()
                    ->title("Faktura {$invoice->invoice_number} byla vystavena.")
                    ->success()
                    ->actions([
                        Action::make('view')
                            ->label('Zobrazit fakturu')
                            ->url(InvoiceResource::getUrl('view', ['record' => $invoice])),
                    ])
                    ->send();
            });
    }
}
