<?php

namespace Tests\Feature\Zone;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZoneInvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRenderer(): void
    {
        // Gotenberg renders the PDF over HTTP — fake the service, not the renderer.
        Http::fake([
            config('services.gotenberg.url').'/*' => Http::response('%PDF-1.7 fake'),
        ]);
    }

    public function test_a_client_can_download_their_own_invoice(): void
    {
        $this->fakeRenderer();

        $client = User::factory()->customer()->create(['email_verified_at' => now()]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($client)
            ->get(route('zone.invoices.download', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString($invoice->invoice_number.'.pdf', $response->headers->get('content-disposition'));
    }

    public function test_another_clients_invoice_is_not_downloadable(): void
    {
        $this->fakeRenderer();

        $stranger = User::factory()->customer()->create(['email_verified_at' => now()]);
        $invoice = Invoice::factory()->create();

        $this->actingAs($stranger)
            ->get(route('zone.invoices.download', $invoice))
            ->assertNotFound();
    }

    public function test_guests_cannot_download_invoices(): void
    {
        $invoice = Invoice::factory()->create();

        $this->get(route('zone.invoices.download', $invoice))
            ->assertRedirect(route('public.login', ['return' => '/muj-ucet/faktury/'.$invoice->getKey().'/stahnout']));
    }
}
