<?php

namespace Tests\Feature\Zone;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Invoices are issued lazily: the client's first download of a settled payment
 * allocates the number, later ones re-render the same invoice.
 */
class ZoneInvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);

        // Gotenberg renders the PDF over HTTP — fake the service, not the renderer.
        Http::fake([
            config('services.gotenberg.url').'/*' => Http::response('%PDF-1.7 fake'),
        ]);
    }

    public function test_the_first_download_issues_the_invoice(): void
    {
        [$client, $payment] = $this->settledPayment();

        $this->assertSame(0, Invoice::query()->count());

        $response = $this->actingAs($client)
            ->get(route('zone.payments.invoice', $payment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $invoice = Invoice::query()->sole();

        $this->assertSame('FF-'.today()->year.'-00001', $invoice->invoice_number);
        $this->assertSame($invoice->getKey(), $payment->fresh()->invoice_id);
        $this->assertStringContainsString($invoice->invoice_number.'.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_a_repeated_download_reuses_the_same_invoice(): void
    {
        [$client, $payment] = $this->settledPayment();

        $this->actingAs($client)->get(route('zone.payments.invoice', $payment))->assertOk();
        $number = Invoice::query()->sole()->invoice_number;

        $this->actingAs($client)->get(route('zone.payments.invoice', $payment))->assertOk();

        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame($number, Invoice::query()->sole()->invoice_number);
    }

    public function test_an_unpaid_payment_has_no_invoice_to_download(): void
    {
        $reservation = $this->reservation();
        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $this->actingAs($reservation->client)
            ->get(route('zone.payments.invoice', $payment))
            ->assertNotFound();

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_a_partially_settled_debt_cannot_be_invoiced_yet(): void
    {
        $reservation = $this->reservation(price: 1000);
        $payment = $this->pay($reservation, 400);

        $this->assertFalse($reservation->fresh()->hasPaidStatus());

        $this->actingAs($reservation->client)
            ->get(route('zone.payments.invoice', $payment))
            ->assertNotFound();

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_another_clients_payment_is_not_downloadable(): void
    {
        [, $payment] = $this->settledPayment();
        $stranger = User::factory()->customer()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->get(route('zone.payments.invoice', $payment))
            ->assertNotFound();

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_guests_cannot_download_invoices(): void
    {
        [, $payment] = $this->settledPayment();

        $this->get(route('zone.payments.invoice', $payment))
            ->assertRedirect(route('public.login', ['return' => '/muj-ucet/platby/'.$payment->getKey().'/faktura']));
    }

    /**
     * @return array{0: User, 1: Payment}
     */
    private function settledPayment(): array
    {
        $reservation = $this->reservation();
        $payment = $this->pay($reservation, 800);

        return [$reservation->client, $payment];
    }

    private function reservation(int $price = 800): Reservation
    {
        $client = User::factory()->customer()->create(['email_verified_at' => now()]);

        return Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'service_id' => Service::factory()->create(['price' => $price])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }

    private function pay(Reservation $reservation, int $amount): Payment
    {
        return $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => $amount,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
