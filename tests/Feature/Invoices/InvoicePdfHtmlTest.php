<?php

namespace Tests\Feature\Invoices;

use App\Enums\PaymentMethod;
use App\Enums\SettingValueType;
use App\Models\CashReceipt;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\Payments\QrPlatba;
use App\Support\Pdf\GotenbergClient;
use App\Support\Pdf\InvoicePdfRenderer;
use App\Support\Pdf\ReceiptPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoicePdfHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_requires_staff(): void
    {
        $invoice = Invoice::factory()->create();

        $this->get(route('invoices.preview', $invoice))->assertForbidden();

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('invoices.preview', $invoice))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('invoices.preview', $invoice))
            ->assertOk();
    }

    public function test_invoice_html_contains_number_parties_items_and_totals(): void
    {
        $invoice = Invoice::factory()->create([
            'client_snapshot' => [
                'name' => 'Jana Kováčová',
                'address' => 'Lípová 12, 150 00 Praha 5',
                'email' => 'jana@example.cz',
                'phone' => null,
                'ico' => null,
                'dic' => null,
            ],
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->getKey(),
            'title' => 'Fyzioterapie individuální',
            'description' => '10:00, Mgr. Petra Nováková',
            'quantity' => 2,
            'unit_price' => 850,
        ]);

        $html = app(InvoicePdfRenderer::class)->html($invoice->fresh());

        $this->assertStringContainsString('FAKTURA', $html);
        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString('Jana Kováčová', $html);
        $this->assertStringContainsString('Fyzioterapie individuální', $html);
        $this->assertStringContainsString('10:00, Mgr. Petra Nováková', $html);
        $this->assertStringContainsString('1 700 Kč', $html);
        $this->assertStringContainsString('Celkem k úhradě', $html);
        $this->assertStringContainsString('Dodavatel', $html);
        $this->assertStringContainsString('Odběratel', $html);
    }

    public function test_qr_and_payment_box_render_for_unpaid_transfer_invoice(): void
    {
        $this->setIban();

        $invoice = Invoice::factory()->create(['payment_method' => PaymentMethod::Qr]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->getKey(), 'unit_price' => 800]);

        $html = app(InvoicePdfRenderer::class)->html($invoice->fresh());

        $this->assertStringContainsString('Platební údaje', $html);
        $this->assertStringContainsString('IBAN', $html);
        $this->assertStringContainsString('Variabilní symbol', $html);
        $this->assertStringContainsString('QR platba', $html);
        $this->assertStringContainsString('data:image/png;base64', $html);
    }

    public function test_qr_uses_snapshot_iban_and_falls_back_to_setting_when_blank(): void
    {
        $this->setIban();

        // Issued before the IBAN setting was configured: blank in the snapshot,
        // so the QR falls back to the current setting instead of never rendering.
        $legacy = Invoice::factory()->create([
            'payment_method' => PaymentMethod::Qr,
            'supplier_snapshot' => ['name' => 'FriendlyFyzio s.r.o.', 'iban' => '', 'bank_account' => ''],
        ]);

        $this->assertStringContainsString(
            'ACC:CZ6508000000192000145399',
            QrPlatba::spaydForInvoice($legacy),
        );

        // A filled snapshot IBAN stays authoritative for the historical document.
        $frozen = Invoice::factory()->create([
            'payment_method' => PaymentMethod::Qr,
            'supplier_snapshot' => ['name' => 'FriendlyFyzio s.r.o.', 'iban' => 'CZ7603000000000123456789'],
        ]);

        $this->assertStringContainsString(
            'ACC:CZ7603000000000123456789',
            QrPlatba::spaydForInvoice($frozen),
        );
    }

    public function test_qr_hidden_for_unpaid_credit_invoice(): void
    {
        $this->setIban();

        $invoice = Invoice::factory()->create(['payment_method' => PaymentMethod::Credit]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->getKey(), 'unit_price' => 800]);

        $html = app(InvoicePdfRenderer::class)->html($invoice->fresh());

        // The payment box still renders for an unpaid non-cash invoice, but the
        // QR code belongs to the QR platba (bank transfer) method only.
        $this->assertStringContainsString('Platební údaje', $html);
        $this->assertStringNotContainsString('data:image/png', $html);
    }

    public function test_previews_render_inside_screen_paper_container(): void
    {
        $invoice = Invoice::factory()->create();
        $receipt = CashReceipt::factory()->create(['invoice_id' => $invoice->getKey()]);

        $admin = User::factory()->admin()->create();

        // Each preview card keeps its sheet's real proportions: A4 portrait
        // for the invoice, A5 landscape for the receipt. The preview also
        // carries the browser-print rules (@page + mirrored running footer)
        // that the Gotenberg document must not contain.
        $this->actingAs($admin)
            ->get(route('invoices.preview', $invoice))
            ->assertOk()
            ->assertSee('@media screen', false)
            ->assertSee('width: 210mm', false)
            ->assertSee('min-height: 297mm', false)
            ->assertSee('size: A4 portrait', false)
            ->assertSee('page-footer', false);

        $this->actingAs($admin)
            ->get(route('cash-receipts.preview', $receipt))
            ->assertOk()
            ->assertSee('width: 210mm', false)
            ->assertSee('min-height: 148mm', false)
            ->assertSee('size: A5 landscape', false)
            ->assertSee('page-footer', false);

        $this->assertStringNotContainsString('@page', app(InvoicePdfRenderer::class)->html($invoice));
        $this->assertStringNotContainsString('page-footer', app(InvoicePdfRenderer::class)->html($invoice));
    }

    public function test_payment_box_always_renders_and_paid_state_never_shows(): void
    {
        $this->setIban();

        // The document is status-agnostic: a paid transfer invoice keeps its full
        // payment box including the QR code, and a cash invoice shows the details
        // too (just without a QR).
        $paid = Invoice::factory()->paid()->create(['payment_method' => PaymentMethod::Qr]);
        InvoiceItem::factory()->create(['invoice_id' => $paid->getKey(), 'unit_price' => 800]);
        $cash = Invoice::factory()->create(['payment_method' => PaymentMethod::Cash]);

        $paidHtml = app(InvoicePdfRenderer::class)->html($paid->fresh());
        $cashHtml = app(InvoicePdfRenderer::class)->html($cash);

        $this->assertStringNotContainsString('UHRAZENO', $paidHtml);
        $this->assertStringContainsString('Platební údaje', $paidHtml);
        $this->assertStringContainsString('data:image/png', $paidHtml);

        $this->assertStringNotContainsString('UHRAZENO', $cashHtml);
        $this->assertStringContainsString('Platební údaje', $cashHtml);
        $this->assertStringNotContainsString('data:image/png', $cashHtml);
    }

    public function test_text_hooks_render_and_vat_note_lives_in_the_running_footer(): void
    {
        $invoice = Invoice::factory()->create([
            'text_before_items' => 'Fakturujeme Vám za poskytnuté služby:',
            'text_after_items' => 'Objednávka č. 42.',
            'footer_note' => 'Děkujeme za Vaši důvěru!',
            'vat_note' => 'Nejsme plátci DPH.',
        ]);

        $html = app(InvoicePdfRenderer::class)->html($invoice);

        $this->assertStringContainsString('Fakturujeme Vám za poskytnuté služby:', $html);
        $this->assertStringContainsString('Objednávka č. 42.', $html);
        $this->assertStringContainsString('Děkujeme za Vaši důvěru!', $html);
        // The supplier info line (incl. the VAT note) moved into the running
        // page footer, next to the page numbers — it is no longer in the body.
        $this->assertStringNotContainsString('Nejsme plátci DPH.', $html);

        $footer = app(InvoicePdfRenderer::class)->footerHtml($invoice);

        $this->assertStringContainsString('Nejsme plátci DPH.', $footer);
        $this->assertStringContainsString('Strana', $footer);
        $this->assertStringContainsString('pageNumber', $footer);
    }

    public function test_vat_recap_renders_when_items_carry_rates(): void
    {
        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->getKey(),
            'unit_price' => 1210,
            'vat_rate' => 21,
        ]);

        $html = app(InvoicePdfRenderer::class)->html($invoice->fresh());

        $this->assertStringContainsString('21 %', $html);
        $this->assertStringContainsString('Základ', $html);
        $this->assertStringContainsString('DPH', $html);
        // 1210 gross at 21 % → 1000 base + 210 vat.
        $this->assertStringContainsString('1 000 Kč', $html);
        $this->assertStringContainsString('210 Kč', $html);
    }

    public function test_receipt_html_matches_design(): void
    {
        $invoice = Invoice::factory()->create();

        $receipt = CashReceipt::factory()->create([
            'invoice_id' => $invoice->getKey(),
            'client_name' => 'Jana Kováčová',
            'purpose' => 'Úhrada faktury č. '.$invoice->invoice_number,
            'received_by' => 'Mgr. Petra Nováková',
            'amount' => 1300,
        ]);

        $html = app(ReceiptPdfRenderer::class)->html($receipt);

        $this->assertStringContainsString('PŘÍJMOVÝ POKLADNÍ DOKLAD', $html);
        $this->assertStringContainsString($receipt->receipt_number, $html);
        $this->assertStringContainsString('Jana Kováčová', $html);
        $this->assertStringContainsString('1 300 Kč', $html);
        $this->assertStringContainsString('slovy:', $html);
        $this->assertStringContainsString('korun českých', $html);
        $this->assertStringContainsString('Přijal', $html);
        $this->assertStringContainsString('Mgr. Petra Nováková', $html);
        $this->assertStringContainsString('Podpis plátce', $html);
        $this->assertStringContainsString('Podpis a razítko dodavatele', $html);
        $this->assertStringContainsString($invoice->invoice_number, $html);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('cash-receipts.preview', $receipt))
            ->assertOk();
    }

    public function test_gotenberg_client_posts_html_and_returns_pdf_bytes(): void
    {
        Http::fake([
            config('services.gotenberg.url').'/*' => Http::response('%PDF-1.7 fake'),
        ]);

        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->getKey()]);

        $pdf = app(InvoicePdfRenderer::class)->render($invoice->fresh());

        $this->assertSame('%PDF-1.7 fake', $pdf);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/forms/chromium/convert/html')
                && $request->isMultipart();
        });
    }

    public function test_gotenberg_client_throws_on_failure(): void
    {
        Http::fake([
            config('services.gotenberg.url').'/*' => Http::response('boom', 500),
        ]);

        $this->expectException(\RuntimeException::class);

        app(GotenbergClient::class)->pdfFromHtml('<html></html>');
    }

    private function setIban(): void
    {
        Setting::updateOrCreate(
            ['key' => 'payments.iban'],
            ['value' => 'CZ6508000000192000145399', 'type' => SettingValueType::Text, 'label' => 'IBAN', 'group' => 'Platby', 'sort' => 0],
        );
    }
}
