<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Actions;

use App\Filament\Support\Schemas\CopyRecipientsFields;
use App\Models\Invoice;
use App\Support\Emails\CopyRecipients;
use App\Support\Invoices\InvoiceNotifier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * E-mails the invoice (CMS "invoice_issued" template + PDF attachment) to the
 * client and moves a fresh invoice to "Odeslaná".
 */
class SendInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendInvoice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Odeslat e-mailem')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('info')
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalHeading('Odeslat fakturu e-mailem')
            ->modalDescription(fn (Invoice $record): string => 'Faktura bude odeslána na '.($record->client?->email ?? '—').' s PDF v příloze.')
            ->modalSubmitActionLabel('Odeslat')
            ->visible(fn (Invoice $record): bool => filled($record->client?->email))
            ->schema(CopyRecipientsFields::make())
            ->action(function (Invoice $record, array $data): void {
                InvoiceNotifier::send($record, CopyRecipients::fromFormData($data));

                Notification::make()
                    ->title('Faktura byla odeslána.')
                    ->success()
                    ->send();
            });
    }
}
