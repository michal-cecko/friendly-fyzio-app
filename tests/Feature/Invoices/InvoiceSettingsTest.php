<?php

namespace Tests\Feature\Invoices;

use App\Enums\DocumentType;
use App\Enums\PayableType;
use App\Models\InvoiceSeries;
use App\Models\Setting;
use App\Support\Settings;
use Database\Seeders\InvoiceSeriesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_seeder_seeds_fakturace_group_idempotently(): void
    {
        $this->seed(SettingsSeeder::class);

        $count = Setting::query()->where('group', 'Fakturace')->count();

        $this->assertGreaterThanOrEqual(12, $count);

        foreach ([
            'invoices.supplier_name',
            'invoices.vat_payer',
            'invoices.due_days',
            'invoices.text_before_items',
            PayableType::Reservation->titleSettingKey(),
            PayableType::CourseEnrollment->descriptionSettingKey(),
            PayableType::STORNO_TITLE_KEY,
            'payments.due_days',
            'payments.no_show_fee_percent',
        ] as $key) {
            $this->assertDatabaseHas('settings', ['key' => $key]);
        }

        $this->seed(SettingsSeeder::class);

        $this->assertSame($count, Setting::query()->where('group', 'Fakturace')->count());
    }

    public function test_settings_accessors_return_defaults_when_missing(): void
    {
        $this->assertSame(14, Settings::invoiceDueDays());
        $this->assertSame(7, Settings::paymentDueDays());
        $this->assertSame(100, Settings::noShowFeePercent());
        $this->assertSame(21, Settings::defaultVatRate());
        $this->assertFalse(Settings::vatPayer());
        $this->assertSame('', Settings::supplierName());
    }

    public function test_invoice_series_seeder_creates_two_defaults_idempotently(): void
    {
        $this->seed(InvoiceSeriesSeeder::class);
        $this->seed(InvoiceSeriesSeeder::class);

        $this->assertDatabaseCount('invoice_series', 2);

        $invoice = InvoiceSeries::query()->where('document_type', DocumentType::Invoice)->sole();
        $receipt = InvoiceSeries::query()->where('document_type', DocumentType::Receipt)->sole();

        $this->assertSame('FF', $invoice->prefix);
        $this->assertTrue($invoice->is_default);
        $this->assertSame('PPD', $receipt->prefix);
        $this->assertTrue($receipt->is_default);
    }
}
