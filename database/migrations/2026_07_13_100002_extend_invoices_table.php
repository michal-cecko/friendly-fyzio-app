<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Supplier identity frozen from Settings at issue time — historical
            // invoices must never change when the clinic's details change.
            $table->json('supplier_snapshot')->nullable();
            // Per-invoice copies of the settings-driven text hooks (editable history).
            $table->text('text_before_items')->nullable();
            $table->text('text_after_items')->nullable();
            $table->string('footer_note')->nullable();
            $table->string('vat_note')->nullable();
            $table->string('variable_symbol')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'supplier_snapshot',
                'text_before_items',
                'text_after_items',
                'footer_note',
                'vat_note',
                'variable_symbol',
            ]);
        });
    }
};
