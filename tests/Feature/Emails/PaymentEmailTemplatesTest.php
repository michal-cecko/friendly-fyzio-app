<?php

namespace Tests\Feature\Emails;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Reservation;
use App\Notifications\TherapistReservationAutoCancelledNotification;
use App\Support\EmailTemplateRenderer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class PaymentEmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_payment_email_templates_seed(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach ([
            EmailTemplateKey::ReservationUnpaid,
            EmailTemplateKey::ReservationNoShow,
            EmailTemplateKey::PaymentReceived,
            EmailTemplateKey::PaymentOverdue,
            EmailTemplateKey::InvoiceIssued,
        ] as $key) {
            $this->assertDatabaseHas('email_templates', ['key' => $key->value]);
        }
    }

    public function test_invoice_items_table_is_inserted_raw_via_html_string(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->getKey(),
            'title' => 'Fyzioterapie individuální',
            'unit_price' => 850,
        ]);

        $table = view('emails.partials.invoice-items-table', ['invoice' => $invoice->fresh()->loadMissing('items')])->render();

        $html = EmailTemplateRenderer::render(
            EmailTemplate::forKey(EmailTemplateKey::InvoiceIssued),
            [
                'jmeno' => 'Jana',
                'cislo_faktury' => $invoice->invoice_number,
                'castka' => '850 Kč',
                'splatnost' => '24. 7. 2026',
                'zpusob_platby' => 'QR platba',
                'polozky_tabulka' => new HtmlString($table),
            ],
        );

        $this->assertStringContainsString('Fyzioterapie individuální', $html);
        $this->assertStringContainsString('Položka', $html);
        // Raw insertion — the table markup must not arrive escaped.
        $this->assertStringNotContainsString('&lt;table', $html);
    }

    public function test_scalar_tokens_stay_escaped(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $html = EmailTemplateRenderer::render(
            EmailTemplate::forKey(EmailTemplateKey::PaymentReceived),
            [
                'jmeno' => '<script>alert(1)</script>',
                'za_co' => 'test',
                'castka' => '100 Kč',
                'datum' => '1. 1. 2026',
                'zpusob_platby' => 'Hotově',
                'cislo_faktury' => '—',
                'odkaz' => '#',
            ],
        );

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_auto_cancel_command_notifies_therapist(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-07-13 12:00:00');

        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'reservation_date' => '2026-07-14',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'created_at' => now()->subDays(3),
        ]);

        $this->artisan('reservations:cancel-unconfirmed')->assertSuccessful();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);

        Notification::assertSentTo(
            $reservation->therapist->user,
            TherapistReservationAutoCancelledNotification::class,
        );

        Carbon::setTestNow();
    }
}
