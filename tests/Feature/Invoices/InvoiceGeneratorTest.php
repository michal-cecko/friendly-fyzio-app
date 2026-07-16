<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\SettingValueType;
use App\Models\ClientProfile;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\Invoices\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InvoiceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceGenerator $generator;

    private InvoiceSeries $series;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(InvoiceGenerator::class);
        $this->series = InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);
    }

    public function test_from_paid_payable_creates_paid_invoice_for_full_due(): void
    {
        $this->setSetting('invoices.item_title_reservation', 'Návštěva: {{ sluzba }} ({{ datum }})');

        $reservation = $this->reservation(['name' => 'Klasická masáž'], price: 800);
        $payment = $this->payFor($reservation, 800);

        $invoice = $this->generator->fromPayable($reservation->fresh());

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(800, $invoice->amount);
        $this->assertSame('FF-'.today()->year.'-00001', $invoice->invoice_number);
        $this->assertTrue($invoice->invoiceable->is($reservation));

        $item = $invoice->items->sole();

        $this->assertStringStartsWith('Návštěva: Klasická masáž (', $item->title);

        $this->assertSame($invoice->getKey(), $payment->fresh()->invoice_id);
        $this->assertSame($payment->variable_symbol, $invoice->variable_symbol);
    }

    public function test_links_all_partial_payments_which_share_one_vs(): void
    {
        $reservation = $this->reservation(price: 1000);

        $first = $this->payFor($reservation, 400);
        $second = $this->payFor($reservation, 600);

        $invoice = $this->generator->fromPayable($reservation->fresh());

        $this->assertSame(1000, $invoice->amount);
        $this->assertSame($invoice->getKey(), $first->fresh()->invoice_id);
        $this->assertSame($invoice->getKey(), $second->fresh()->invoice_id);
        $this->assertSame($first->variable_symbol, $second->fresh()->variable_symbol);
        $this->assertSame($first->variable_symbol, $invoice->variable_symbol);
    }

    public function test_refuses_unpaid_payable(): void
    {
        $reservation = $this->reservation(price: 800);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('zaplacenému');

        $this->generator->fromPayable($reservation);
    }

    public function test_refuses_second_invoice_for_payable(): void
    {
        $reservation = $this->reservation(price: 800);
        $this->payFor($reservation, 800);

        $this->generator->fromPayable($reservation->fresh());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('už má vystavenou');

        $this->generator->fromPayable($reservation->fresh());
    }

    public function test_refuses_zero_due(): void
    {
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create(['price' => 0])->getKey(),
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('žádnou částku');

        $this->generator->fromPayable($enrollment);
    }

    public function test_storno_fee_invoice_for_cancelled_paid_reservation(): void
    {
        $reservation = $this->reservation(['name' => 'Lymfodrenáž'], price: 800, status: ReservationStatus::Cancelled);
        $this->payFor($reservation, 400);

        $invoice = $this->generator->fromPayable($reservation->fresh());

        $this->assertSame(400, $invoice->amount);
        $this->assertSame('Storno poplatek – Lymfodrenáž', $invoice->items->sole()->title);
    }

    public function test_hand_marked_paid_enrollment_without_payments_gets_backing_paid_payment(): void
    {
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create(['price' => 1200])->getKey(),
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now()->subDay(),
        ]);

        $invoice = $this->generator->fromPayable($enrollment);

        $backing = $invoice->payments()->sole();

        $this->assertSame(PaymentStatus::Paid, $backing->status);
        $this->assertSame(PaymentMethod::Qr, $backing->method);
        $this->assertSame(1200, $backing->amount);
        $this->assertTrue($backing->payable->is($enrollment));
        $this->assertSame($backing->variable_symbol, $invoice->variable_symbol);
        // Fabricated Qr thread must not have produced a PPD.
        $this->assertNull($backing->cashReceipt()->first());
    }

    public function test_from_payment_delegates_to_the_payable(): void
    {
        $reservation = $this->reservation(price: 800);
        $payment = $this->payFor($reservation, 800);

        $invoice = $this->generator->fromPayment($payment->fresh());

        $this->assertTrue($invoice->invoiceable->is($reservation));
        $this->assertSame(800, $invoice->amount);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_from_payment_for_unlinked_payment_documents_single_transfer(): void
    {
        $payment = Payment::factory()->paid()->create(['amount' => 650]);

        $invoice = $this->generator->fromPayment($payment);

        $this->assertSame('Platba č. '.$payment->number, $invoice->items->sole()->title);
        $this->assertSame(650, $invoice->amount);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame($payment->variable_symbol, $invoice->variable_symbol);
        $this->assertSame($invoice->getKey(), $payment->fresh()->invoice_id);
    }

    public function test_from_payment_refuses_unpaid_unlinked_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('přijaté platbě');

        $this->generator->fromPayment($payment);
    }

    public function test_cash_paid_payment_gets_its_receipt_linked_to_the_invoice(): void
    {
        InvoiceSeries::factory()->receipt()->asDefault()->create(['prefix' => 'PPD']);

        $reservation = $this->reservation(price: 800);
        $payment = $this->payFor($reservation, 800, PaymentMethod::Cash);

        $invoice = $this->generator->fromPayable($reservation->fresh());

        $receipt = $payment->fresh()->cashReceipt()->first();

        $this->assertNotNull($receipt);
        $this->assertSame($invoice->getKey(), $receipt->invoice_id);
    }

    public function test_supplier_and_client_snapshots_are_frozen(): void
    {
        $this->setSetting('invoices.supplier_name', 'FriendlyFyzio s.r.o.');
        $this->setSetting('web.company_id', '06816967');

        $client = User::factory()->customer()->create();
        ClientProfile::factory()->create([
            'user_id' => $client->getKey(),
            'billing_name' => 'Firma ABC s.r.o.',
            'company_ico' => '12345678',
        ]);

        $reservation = $this->reservation(price: 800, reservation: ['client_id' => $client->getKey()]);
        $this->payFor($reservation, 800);

        $invoice = $this->generator->fromPayable($reservation->fresh());

        $this->assertSame('FriendlyFyzio s.r.o.', $invoice->supplier_snapshot['name']);
        $this->assertSame('06816967', $invoice->supplier_snapshot['ico']);
        $this->assertSame('Firma ABC s.r.o.', $invoice->client_snapshot['name']);
        $this->assertSame('12345678', $invoice->client_snapshot['ico']);
    }

    public function test_vat_payer_mode_assigns_default_rate_and_drops_vat_note(): void
    {
        $this->setSetting('invoices.vat_payer', '1', SettingValueType::Boolean);
        $this->setSetting('invoices.default_vat_rate', '21', SettingValueType::Integer);

        $reservation = $this->reservation(price: 800);
        $this->payFor($reservation, 800);

        $invoice = $this->generator->fromPayable($reservation->fresh());

        $this->assertSame(21, $invoice->items->sole()->vat_rate);
        $this->assertNull($invoice->vat_note);
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  array<string, mixed>  $reservation
     */
    private function reservation(
        array $service = [],
        int $price = 800,
        ReservationStatus $status = ReservationStatus::Confirmed,
        array $reservation = [],
    ): Reservation {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => $price, ...$service])->getKey(),
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            ...$reservation,
        ]);
    }

    private function payFor(Reservation $reservation, int $amount, PaymentMethod $method = PaymentMethod::Qr): Payment
    {
        return $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => $amount,
            'method' => $method,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    private function setSetting(string $key, string $value, SettingValueType $type = SettingValueType::Text): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'label' => $key,
                'group' => 'Test',
                'sort' => 0,
            ],
        );
    }
}
