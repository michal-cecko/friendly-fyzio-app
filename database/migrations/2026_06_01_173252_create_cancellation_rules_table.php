<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('cancel_before_hours');
            $table->integer('auto_cancel_after_days')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_rules');
    }
};
