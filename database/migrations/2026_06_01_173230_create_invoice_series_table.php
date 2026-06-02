<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('prefix');
            $table->integer('current_number')->default(0);
            $table->boolean('reset_yearly')->default(true);
            $table->integer('last_reset_year')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_series');
    }
};
