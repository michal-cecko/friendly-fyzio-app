<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_series', function (Blueprint $table): void {
            // 'invoice' | 'receipt' — receipts (PPD) get their own independent series.
            $table->string('document_type')->default('invoice')->index();
            $table->unsignedSmallInteger('padding')->default(5);
            $table->string('format')->default('{PREFIX}-{YEAR}-{SEQ}');
            // Preselected series per document type; uniqueness enforced in the model.
            $table->boolean('is_default')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_series', function (Blueprint $table): void {
            $table->dropColumn(['document_type', 'padding', 'format', 'is_default']);
        });
    }
};
