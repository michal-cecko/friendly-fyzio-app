<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Models\InvoiceSeries;
use Illuminate\Database\Seeder;

/**
 * The two default numbering series: invoices (FF) and cash receipts (PPD).
 * Idempotent — reseeding never resets a live counter.
 */
class InvoiceSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $series = [
            [
                'prefix' => 'FF',
                'document_type' => DocumentType::Invoice,
                'name' => 'Faktury',
            ],
            [
                'prefix' => 'PPD',
                'document_type' => DocumentType::Receipt,
                'name' => 'Pokladní doklady',
            ],
        ];

        foreach ($series as $attributes) {
            InvoiceSeries::query()->firstOrCreate(
                [
                    'prefix' => $attributes['prefix'],
                    'document_type' => $attributes['document_type'],
                ],
                [
                    'name' => $attributes['name'],
                    'current_number' => 0,
                    'reset_yearly' => true,
                    'padding' => 5,
                    'format' => '{PREFIX}-{YEAR}-{SEQ}',
                    'is_default' => true,
                ],
            );
        }
    }
}
