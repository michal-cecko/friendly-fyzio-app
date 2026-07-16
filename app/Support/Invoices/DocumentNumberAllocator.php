<?php

namespace App\Support\Invoices;

use App\Enums\DocumentType;
use App\Models\InvoiceSeries;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Allocates document numbers from a series, atomically. The series row is locked
 * for the duration of the (possibly outer) transaction, so a number is only ever
 * consumed together with its committed document.
 */
final class DocumentNumberAllocator
{
    public function allocate(InvoiceSeries $series, ?CarbonInterface $issuedAt = null): string
    {
        return DB::transaction(function () use ($series, $issuedAt): string {
            $locked = InvoiceSeries::query()
                ->whereKey($series->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $year = ($issuedAt ?? today())->year;

            if ($locked->reset_yearly && $locked->last_reset_year !== $year) {
                $locked->current_number = 0;
                $locked->last_reset_year = $year;
            }

            $locked->current_number++;
            $locked->save();

            return $this->format($locked, $year, $locked->current_number);
        });
    }

    /**
     * The next number WITHOUT incrementing — for read-only previews in the admin.
     */
    public function preview(InvoiceSeries $series): string
    {
        $year = today()->year;

        $next = $series->reset_yearly && $series->last_reset_year !== $year
            ? 1
            : $series->current_number + 1;

        return $this->format($series, $year, $next);
    }

    public function defaultSeries(DocumentType $type): ?InvoiceSeries
    {
        return InvoiceSeries::query()
            ->where('document_type', $type)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->first();
    }

    private function format(InvoiceSeries $series, int $year, int $number): string
    {
        return strtr((string) $series->format, [
            '{PREFIX}' => (string) $series->prefix,
            '{YEAR}' => (string) $year,
            '{SEQ}' => str_pad((string) $number, max(1, (int) $series->padding), '0', STR_PAD_LEFT),
        ]);
    }
}
