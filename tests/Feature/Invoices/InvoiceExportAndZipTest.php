<?php

namespace Tests\Feature\Invoices;

use App\Filament\Clusters\Finance\Resources\Invoices\Pages\ListInvoices;
use App\Jobs\BuildInvoicesZipJob;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Support\Pdf\InvoicePdfRenderer;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceExportAndZipTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_zip_job_stores_archive_and_notifies_initiator(): void
    {
        Storage::fake('local');
        Http::fake([
            config('services.gotenberg.url').'/*' => Http::response('%PDF-1.7 fake'),
        ]);

        $invoices = Invoice::factory()->count(2)->create();
        $invoices->each(fn (Invoice $invoice) => InvoiceItem::factory()->create([
            'invoice_id' => $invoice->getKey(),
        ]));

        (new BuildInvoicesZipJob($invoices->modelKeys(), (string) $this->admin->getKey()))
            ->handle(app(InvoicePdfRenderer::class));

        $files = Storage::disk('local')->allFiles('invoice-exports');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.zip', $files[0]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->admin->getKey(),
        ]);
    }

    public function test_download_route_streams_for_staff_and_rejects_others(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('invoice-exports/abc/faktury.zip', 'zip-bytes');

        $url = route('invoices.export-download', ['path' => base64_encode('invoice-exports/abc/faktury.zip')]);

        $this->get($url)->assertOk();

        $this->actingAs(User::factory()->customer()->create());

        $this->get($url)->assertForbidden();
    }

    public function test_download_route_rejects_paths_outside_exports_folder(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('secrets.txt', 'nope');

        $this->get(route('invoices.export-download', ['path' => base64_encode('secrets.txt')]))
            ->assertNotFound();

        $this->get(route('invoices.export-download', ['path' => base64_encode('invoice-exports/../secrets.txt')]))
            ->assertNotFound();
    }

    public function test_prune_command_deletes_only_old_exports(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('invoice-exports/old/faktury.zip', 'old');
        Storage::disk('local')->put('invoice-exports/new/faktury.zip', 'new');

        // Backdate the "old" file beyond the 24h window.
        touch(
            Storage::disk('local')->path('invoice-exports/old/faktury.zip'),
            now()->subHours(30)->getTimestamp(),
        );

        $this->artisan('invoices:prune-exports')->assertSuccessful();

        Storage::disk('local')->assertMissing('invoice-exports/old/faktury.zip');
        Storage::disk('local')->assertExists('invoice-exports/new/faktury.zip');
    }

    public function test_bulk_action_dispatches_zip_job(): void
    {
        Bus::fake([BuildInvoicesZipJob::class]);

        $invoices = Invoice::factory()->count(2)->create();

        Livewire::test(ListInvoices::class)
            ->selectTableRecords($invoices->modelKeys())
            ->callAction(
                TestAction::make('downloadInvoicesZip')->table()->bulk(),
            )
            ->assertHasNoActionErrors();

        Bus::assertDispatched(BuildInvoicesZipJob::class, function (BuildInvoicesZipJob $job) use ($invoices): bool {
            return count($job->invoiceIds) === $invoices->count()
                && $job->userId === (string) $this->admin->getKey();
        });
    }
}
