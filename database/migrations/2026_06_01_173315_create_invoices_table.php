<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('series_id')->nullable()->constrained('invoice_series')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->foreignUuid('client_id')->constrained('users')->cascadeOnDelete();
            $table->json('client_snapshot');
            $table->integer('amount');
            $table->string('status')->default('new');
            $table->string('payment_method')->nullable();
            $table->date('issued_at');
            $table->date('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('invoiceable_type')->nullable();
            $table->uuid('invoiceable_id')->nullable();
            $table->timestamps();

            $table->index(['invoiceable_type', 'invoiceable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
