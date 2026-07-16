<?php

namespace Tests\Feature\Invoices;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSeries;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceSeries $series;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());

        $this->series = InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);
    }

    public function test_admin_can_list_invoices(): void
    {
        $invoices = Invoice::factory()->count(3)->create();

        Livewire::test(ListInvoices::class)
            ->assertCanSeeTableRecords($invoices);
    }

    public function test_admin_can_create_standalone_invoice(): void
    {
        $client = User::factory()->customer()->create();

        Livewire::test(CreateInvoice::class)
            ->fillForm([
                'series_id' => $this->series->getKey(),
                'client_id' => $client->getKey(),
                'payment_method' => PaymentMethod::Qr->value,
                'issued_at' => today()->toDateString(),
                'due_at' => today()->addDays(14)->toDateString(),
                'client_snapshot.name' => 'Firma ABC s.r.o.',
                'client_snapshot.ico' => '12345678',
                'items' => [
                    [
                        'title' => 'Pronájem sálu',
                        'description' => 'červenec 2026',
                        'quantity' => 2,
                        'unit_price' => 500,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::query()->sole();

        $this->assertSame('FF-'.today()->year.'-00001', $invoice->invoice_number);
        $this->assertSame('Firma ABC s.r.o.', $invoice->client_snapshot['name']);
        $this->assertNotNull($invoice->supplier_snapshot);
        $this->assertNull($invoice->invoiceable_type);
        $this->assertSame(1000, $invoice->amount);
        $this->assertSame('Pronájem sálu', $invoice->items->sole()->title);

        // The backing payment is the invoice's payment thread + VS source.
        $backing = $invoice->payments()->sole();

        $this->assertSame(PaymentStatus::Unpaid, $backing->status);
        $this->assertSame(1000, $backing->amount);
        $this->assertSame($backing->variable_symbol, $invoice->variable_symbol);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateInvoice::class)
            ->fillForm([
                'client_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['client_id' => 'required']);
    }

    public function test_series_select_rejects_receipt_series(): void
    {
        $receiptSeries = InvoiceSeries::factory()->receipt()->create();
        $client = User::factory()->customer()->create();

        Livewire::test(CreateInvoice::class)
            ->fillForm([
                'series_id' => $receiptSeries->getKey(),
                'client_id' => $client->getKey(),
                'client_snapshot.name' => 'Test',
                'items' => [
                    ['title' => 'Položka', 'quantity' => 1, 'unit_price' => 100],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['series_id']);
    }

    public function test_admin_can_edit_texts_and_items(): void
    {
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->getKey(),
            'title' => 'Původní název',
            'quantity' => 1,
            'unit_price' => 800,
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->fillForm([
                'text_after_items' => 'Objednávka č. 42.',
                'items' => [
                    [
                        'title' => 'Upravený název položky',
                        'description' => null,
                        'quantity' => 2,
                        'unit_price' => 800,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice->refresh();

        $this->assertSame('Objednávka č. 42.', $invoice->text_after_items);
        $this->assertSame('Upravený název položky', $invoice->items->sole()->title);
        $this->assertSame(1600, $invoice->amount);
    }

    public function test_customer_cannot_access_invoices(): void
    {
        $this->actingAs(User::factory()->customer()->create());

        $this->get(InvoiceResource::getUrl('index'))->assertForbidden();
    }
}
