<?php

namespace Tests\Feature\Invoices;

use App\Enums\DocumentType;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages\CreateInvoiceSeries;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages\EditInvoiceSeries;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages\ListInvoiceSeries;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceSeriesResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_list_and_create_series(): void
    {
        $existing = InvoiceSeries::factory()->count(2)->create();

        Livewire::test(ListInvoiceSeries::class)
            ->assertCanSeeTableRecords($existing);

        Livewire::test(CreateInvoiceSeries::class)
            ->fillForm([
                'name' => 'Kurzy',
                'prefix' => 'KU',
                'document_type' => DocumentType::Invoice->value,
                'format' => '{PREFIX}-{YEAR}-{SEQ}',
                'padding' => 3,
                'reset_yearly' => true,
                'is_default' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(InvoiceSeries::class, [
            'prefix' => 'KU',
            'padding' => 3,
        ]);
    }

    public function test_saving_default_unsets_previous_default_of_same_type(): void
    {
        $first = InvoiceSeries::factory()->asDefault()->create();
        $receipt = InvoiceSeries::factory()->receipt()->asDefault()->create();

        $second = InvoiceSeries::factory()->asDefault()->create();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        // The receipt default is untouched — uniqueness is per document type.
        $this->assertTrue($receipt->fresh()->is_default);
    }

    public function test_delete_hidden_for_series_with_documents(): void
    {
        $used = InvoiceSeries::factory()->create();
        Invoice::factory()->create(['series_id' => $used->getKey()]);

        $unused = InvoiceSeries::factory()->create();

        Livewire::test(ListInvoiceSeries::class)
            ->assertActionHidden(TestAction::make('delete')->table($used))
            ->assertActionVisible(TestAction::make('delete')->table($unused));
    }

    public function test_current_number_cannot_be_edited_via_form(): void
    {
        $series = InvoiceSeries::factory()->create(['current_number' => 5]);

        Livewire::test(EditInvoiceSeries::class, ['record' => $series->getKey()])
            ->fillForm(['name' => 'Přejmenováno'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(5, $series->fresh()->current_number);
        $this->assertSame('Přejmenováno', $series->fresh()->name);
    }
}
