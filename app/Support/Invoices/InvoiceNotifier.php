<?php

namespace App\Support\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceIssuedNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Emails\CopyRecipients;
use App\Support\Emails\SentEmailReceipt;

/**
 * Explicit invoice e-mails. Deliberately NOT observer-driven: the issuing and
 * sending actions call this so the admin's "notify client" toggle is always
 * honored and an invoice PDF is never attached as a hidden side effect (a
 * client who issues their own invoice from the zone gets no e-mail at all).
 */
final class InvoiceNotifier
{
    /**
     * Sends the invoice to its client and moves a fresh invoice to "Odeslaná".
     * Returns false when there is no address to send to.
     */
    public static function send(Invoice $invoice, ?CopyRecipients $copies = null, bool $receipt = true): bool
    {
        $client = $invoice->client;

        if ($client === null || blank($client->email)) {
            return false;
        }

        $client->notify(new InvoiceIssuedNotification($invoice, $copies));

        if ($receipt) {
            SentEmailReceipt::forCurrentUser('Faktura '.$invoice->invoice_number);
        }

        LogActivity::record('invoice_issued', $invoice, 'Faktura odeslána e-mailem', [
            'notified_client' => true,
        ]);

        if ($invoice->status === InvoiceStatus::New) {
            $invoice->update(['status' => InvoiceStatus::Sent]);
        }

        return true;
    }
}
