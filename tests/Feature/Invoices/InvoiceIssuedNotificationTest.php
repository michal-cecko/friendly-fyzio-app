<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Notifications\InvoiceIssuedNotification;
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
