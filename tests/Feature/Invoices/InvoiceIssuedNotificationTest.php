<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Clusters\Finance\Resources\Payments\Pages\ListPayments;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Support\Invoices\InvoiceGenerator;
use Database\Seeders\EmailTemplateSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceIssuedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_send_action_notifies_client_and_marks_sent(): void
    {
        Notification::fake();

        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::New]);

        Livewire::test(ListInvoices::class)
            ->callAction(TestAction::make('sendInvoice')->table($invoice))
            ->assertHasNoActionErrors();

        Notification::assertSentTo($invoice->client, InvoiceIssuedNotification::class);

        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
    }

    public function test_generate_action_emails_the_invoice_when_notify_is_on(): void
    {
        Notification::fake();

        $payment = $this->settledPayment();

        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('generateInvoice')->table($payment), [
                'series_id' => InvoiceSeries::query()->sole()->getKey(),
                'issued_at' => today(),
                'due_at' => today()->addWeek(),
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo($payment->client, InvoiceIssuedNotification::class);
    }

    public function test_generate_action_stays_silent_when_notify_is_off(): void
    {
        Notification::fake();

        $payment = $this->settledPayment();

        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('generateInvoice')->table($payment), [
                'series_id' => InvoiceSeries::query()->sole()->getKey(),
                'issued_at' => today(),
                'due_at' => today()->addWeek(),
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertNotNull($payment->fresh()->invoice_id);

        Notification::assertNothingSentTo($payment->client);
    }

    public function test_payment_received_mail_does_not_attach_the_invoice(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $payment = $this->settledPayment();
        $invoice = app(InvoiceGenerator::class)->fromPayment($payment);

        $mail = (new PaymentReceivedNotification($payment->fresh()))->toMail($payment->client);

        $this->assertNotNull($invoice->invoice_number);
        $this->assertSame([], $mail->rawAttachments);
    }

    private function settledPayment(): Payment
    {
        InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);

        $reservation = Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => 800])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        return $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function test_mail_message_attaches_invoice_pdf(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        Http::fake([
            config('services.gotenberg.url').'/*' => Http::response('%PDF-1.7 fake'),
        ]);

        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->getKey()]);

        $mail = (new InvoiceIssuedNotification($invoice->fresh()))->toMail($invoice->client);

        $this->assertCount(1, $mail->rawAttachments);
        $this->assertSame("{$invoice->invoice_number}.pdf", $mail->rawAttachments[0]['name']);
        $this->assertSame('%PDF-1.7 fake', $mail->rawAttachments[0]['data']);
    }
}
