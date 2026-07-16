<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A receipt documents received cash and can exist before (or without) an
        // invoice, so the invoice link becomes optional. Separate Schema::table
        // calls keep the SQLite table rebuilds ordered correctly.
        Schema::table('cash_receipts', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('cash_receipts', function (Blueprint $table): void {
            $table->uuid('invoice_id')->nullable()->change();
        });

        Schema::table('cash_receipts', function (Blueprint $table): void {
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreignUuid('series_id')->nullable()->constrained('invoice_series')->nullOnDelete();
            $table->foreignUuid('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
            // Snapshot of the payer name at issue time (editable on the document).
            $table->string('client_name')->nullable();
            $table->string('purpose')->nullable();
            // The "Přijal" person printed on the PPD.
            $table->string('received_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cash_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('series_id');
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn(['client_name', 'purpose', 'received_by']);
        });

        Schema::table('cash_receipts', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('cash_receipts', function (Blueprint $table): void {
            $table->uuid('invoice_id')->nullable(false)->change();
        });

        Schema::table('cash_receipts', function (Blueprint $table): void {
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }
};
