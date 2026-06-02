<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('method');
            $table->string('variable_symbol')->nullable();
            $table->string('status')->default('pending');
            $table->foreignUuid('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payable_type')->nullable();
            $table->uuid('payable_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
