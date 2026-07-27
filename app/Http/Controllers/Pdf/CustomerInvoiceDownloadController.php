<?php

namespace App\Http\Controllers\Pdf;

use App\Contracts\Payable;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ActivityLog\LogActivity;
use App\Support\Invoices\InvoiceGenerator;
use App\Support\Pdf\InvoicePdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lets a client download the invoice for one of their own settled payments.
 * Invoices are issued lazily — the first download allocates the number and
 * persists the invoice, every later one just re-renders it (PDFs are never
 * stored). Payments belonging to anyone else, or ones staff have not recorded
 * as received yet, are indistinguishable from missing ones (404).
 */
class CustomerInvoiceDownloadController extends Controller
{
    public function __invoke(Request $request, Payment $payment): StreamedResponse
    {
        abort_unless($payment->client_id === $request->user()->getKey(), 404);
        abort_unless($payment->status === PaymentStatus::Paid, 404);

        $invoice = $this->resolveInvoice($payment);

        return response()->streamDownload(
            fn () => print (app(InvoicePdfRenderer::class)->render($invoice)),
            "{$invoice->invoice_number}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Returns the payment's invoice, issuing one on the spot when the debt has
     * none. The lock keeps a double-click from burning two invoice numbers.
     */
    private function resolveInvoice(Payment $payment): Invoice
    {
        return Cache::lock('payment-invoice:'.$payment->getKey(), 10)->block(5, function () use ($payment): Invoice {
            $payment->refresh();

            $existing = $payment->invoice;

            if ($existing !== null) {
                return $existing;
            }

            $payable = $payment->payable;

            if ($payable instanceof Payable) {
                $onPayable = $payable->invoice()->first();

                if ($onPayable !== null) {
                    // The debt is already invoiced — this payment simply joins
                    // the thread (the same link PaymentObserver makes on create).
                    $payment->forceFill(['invoice_id' => $onPayable->getKey()])->saveQuietly();

                    return $onPayable;
                }
            }

            try {
                $invoice = app(InvoiceGenerator::class)->fromPayment($payment);
            } catch (InvalidArgumentException|RuntimeException) {
                // Debt not fully settled, or no invoice series configured —
                // nothing the client can act on, so it reads as "no invoice".
                abort(404);
            }

            LogActivity::record('invoice_issued', $invoice, 'Fakturu si vystavil klient', [
                'notified_client' => false,
            ]);

            return $invoice;
        });
    }
}
