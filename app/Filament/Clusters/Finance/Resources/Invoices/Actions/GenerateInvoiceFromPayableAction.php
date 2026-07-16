<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Actions;

use App\Contracts\Payable;
use App\Enums\DocumentType;
use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Models\InvoiceSeries;
use App\Support\Invoices\DocumentNumberAllocator;
use App\Support\Invoices\InvoiceGenerator;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Issues the payable's invoice — available only once the record is PAID (the
 * invoice documents a settled supply for its full amount due; all of the debt's
 * payments link to it and share its variable symbol).
 */
class GenerateInvoiceFromPayableAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateInvoiceFromPayable';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Vystavit fakturu')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->modalHeading('Vystavit fakturu')
            ->modalIcon(Heroicon::OutlinedDocumentText)
            ->modalSubmitActionLabel('Vystavit')
            ->visible(fn (Model $record): bool => $record instanceof Payable
                && $record->hasPaidStatus()
                && $record->invoice()->doesntExist()
                && $record->paymentAmountDue() > 0)
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
            ->action(function (Model $record, array $data): void {
                if (! $record instanceof Payable) {
                    return;
                }

                $invoice = app(InvoiceGenerator::class)->fromPayable(
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
