<?php

namespace Tests\Feature\CashReceipts;

use App\Filament\Clusters\Finance\Resources\CashReceipts\Pages\EditCashReceipt;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Pages\ListCashReceipts;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Pages\ViewCashReceipt;
use App\Filament\Clusters\Finance\Resources\Payments\Pages\ListPayments;
use App\Models\CashReceipt;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashReceiptResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_list_receipts(): void
    {
        $receipts = CashReceipt::factory()->count(3)->create();

        Livewire::test(ListCashReceipts::class)
            ->assertCanSeeTableRecords($receipts);
    }

    public function test_view_page_renders_with_links(): void
    {
        $receipt = CashReceipt::factory()->create();

        Livewire::test(ViewCashReceipt::class, ['record' => $receipt->getKey()])
            ->assertOk();
    }

    public function test_generate_action_on_cash_payment_row(): void
    {
        // Payment created BEFORE any receipt series exists — the observer skips
        // the auto-PPD, leaving the manual action visible.
        $payment = Payment::factory()->cash()->paid()->create();

        $series = InvoiceSeries::factory()->receipt()->asDefault()->create(['prefix' => 'PPD']);

        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('generateCashReceipt')->table($payment), [
                'series_id' => $series->getKey(),
            ])
            ->assertHasNoActionErrors();

        $receipt = $payment->cashReceipt()->first();

        $this->assertNotNull($receipt);
        $this->assertStringStartsWith('PPD-', $receipt->receipt_number);
    }

    public function test_generate_action_hidden_for_qr_payment(): void
    {
        InvoiceSeries::factory()->receipt()->asDefault()->create();

        $payment = Payment::factory()->paid()->create();

        Livewire::test(ListPayments::class)
            ->assertActionHidden(TestAction::make('generateCashReceipt')->table($payment));
    }

    public function test_admin_can_edit_receipt_fields(): void
    {
        $receipt = CashReceipt::factory()->create();

        Livewire::test(EditCashReceipt::class, ['record' => $receipt->getKey()])
            ->fillForm([
                'client_name' => 'Jana Kováčová',
                'purpose' => 'Úhrada terapie',
                'received_by' => 'Mgr. Lucie Fičkerová',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $receipt->refresh();

        $this->assertSame('Jana Kováčová', $receipt->client_name);
        $this->assertSame('Úhrada terapie', $receipt->purpose);
        $this->assertSame('Mgr. Lucie Fičkerová', $receipt->received_by);
    }
}
