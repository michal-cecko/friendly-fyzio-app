<?php

namespace Tests\Feature\Invoices;

use App\Enums\DocumentType;
use App\Models\InvoiceSeries;
use App\Support\Invoices\DocumentNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * True lock contention is Postgres-only (lockForUpdate is a no-op on the SQLite
 * test database); these tests assert the sequencing semantics.
 */
class DocumentNumberAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private DocumentNumberAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocator = app(DocumentNumberAllocator::class);
    }

    public function test_allocates_sequential_numbers(): void
    {
        $series = InvoiceSeries::factory()->create(['prefix' => 'FF', 'padding' => 5]);
        $year = today()->year;

        $this->assertSame("FF-{$year}-00001", $this->allocator->allocate($series));
        $this->assertSame("FF-{$year}-00002", $this->allocator->allocate($series));
        $this->assertSame(2, $series->fresh()->current_number);
    }

    public function test_pads_sequence_to_series_padding(): void
    {
        $series = InvoiceSeries::factory()->create(['prefix' => 'FT', 'padding' => 3]);
        $year = today()->year;

        $this->assertSame("FT-{$year}-001", $this->allocator->allocate($series));
    }

    public function test_format_tokens_are_substituted(): void
    {
        $series = InvoiceSeries::factory()->create([
            'prefix' => 'AB',
            'format' => '{PREFIX}/{SEQ}',
            'padding' => 4,
        ]);

        $this->assertSame('AB/0001', $this->allocator->allocate($series));
    }

    public function test_yearly_reset_restarts_sequence(): void
    {
        Carbon::setTestNow('2026-12-30 10:00:00');

        $series = InvoiceSeries::factory()->create(['prefix' => 'FF', 'reset_yearly' => true]);

        $this->allocator->allocate($series);
        $this->assertSame('FF-2026-00002', $this->allocator->allocate($series));

        Carbon::setTestNow('2027-01-02 10:00:00');

        $this->assertSame('FF-2027-00001', $this->allocator->allocate($series));
        $this->assertSame(2027, $series->fresh()->last_reset_year);

        Carbon::setTestNow();
    }

    public function test_no_reset_when_reset_yearly_disabled(): void
    {
        Carbon::setTestNow('2026-12-30 10:00:00');

        $series = InvoiceSeries::factory()->create([
            'prefix' => 'ST',
            'reset_yearly' => false,
            'format' => '{PREFIX}-{SEQ}',
        ]);

        $this->allocator->allocate($series);
        $this->allocator->allocate($series);

        Carbon::setTestNow('2027-01-02 10:00:00');

        $this->assertSame('ST-00003', $this->allocator->allocate($series));

        Carbon::setTestNow();
    }

    public function test_preview_does_not_increment(): void
    {
        $series = InvoiceSeries::factory()->create(['prefix' => 'FF']);
        $year = today()->year;

        $this->assertSame("FF-{$year}-00001", $this->allocator->preview($series));
        $this->assertSame(0, $series->fresh()->current_number);

        $this->allocator->allocate($series);

        $this->assertSame("FF-{$year}-00002", $this->allocator->preview($series->fresh()));
    }

    public function test_default_series_resolution_prefers_is_default_per_document_type(): void
    {
        $plain = InvoiceSeries::factory()->create(['created_at' => now()->subDay()]);
        $default = InvoiceSeries::factory()->asDefault()->create();
        $receipt = InvoiceSeries::factory()->receipt()->asDefault()->create();

        $this->assertTrue($this->allocator->defaultSeries(DocumentType::Invoice)->is($default));
        $this->assertTrue($this->allocator->defaultSeries(DocumentType::Receipt)->is($receipt));

        $default->delete();

        $this->assertTrue($this->allocator->defaultSeries(DocumentType::Invoice)->is($plain));
    }

    public function test_allocation_rolls_back_with_the_outer_transaction(): void
    {
        $series = InvoiceSeries::factory()->create();

        try {
            DB::transaction(function () use ($series): void {
                $this->allocator->allocate($series);

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // Expected — the document creation failed after the number was taken.
        }

        $this->assertSame(0, $series->fresh()->current_number);
    }
}
