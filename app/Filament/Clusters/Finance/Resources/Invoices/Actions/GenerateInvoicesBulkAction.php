<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Actions;

use App\Contracts\Payable;
use App\Enums\DocumentType;
use App\Models\InvoiceSeries;
use App\Support\Invoices\DocumentNumberAllocator;
use App\Support\Invoices\InvoiceGenerator;
use App\Support\Settings;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Bulk counterpart of {@see GenerateInvoiceFromPayableAction}: issues an invoice
 * for every selected paid payable that doesn't have one yet, all in the chosen
 * series with a shared issue/due date. Ineligible records (unpaid, already
 * invoiced, zero amount) are skipped.
 */
class GenerateInvoicesBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'generateInvoices';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Vystavit faktury')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->modalHeading('Vystavit faktury')
            ->modalIcon(Heroicon::OutlinedDocumentText)
            ->modalDescription('Vystaví fakturu ke každému vybranému zaplacenému záznamu, který ji ještě nemá.')
            ->modalSubmitActionLabel('Vystavit')
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
            ->action(function (Collection $records, array $data): void {
                $series = InvoiceSeries::query()->findOrFail($data['series_id']);
                $issuedAt = Carbon::parse($data['issued_at']);
                $dueAt = Carbon::parse($data['due_at']);
                $generator = app(InvoiceGenerator::class);
                $issued = 0;

                $records->each(function (Model $record) use ($generator, $series, $issuedAt, $dueAt, &$issued): void {
                    if (! $record instanceof Payable
                        || ! $record->hasPaidStatus()
                        || $record->invoice()->exists()
                        || $record->paymentAmountDue() <= 0) {
                        return;
                    }

                    $generator->fromPayable($record, $series, $issuedAt, $dueAt);
                    $issued++;
                });

                Notification::make()
                    ->title("Vystaveno faktur: {$issued}")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
