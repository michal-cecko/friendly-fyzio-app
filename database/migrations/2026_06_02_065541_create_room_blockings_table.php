<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('room_blockings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->boolean('is_recurring')->default(false);
            // Recurring blockings:
            $table->string('day_of_week')->nullable();
            $table->string('week_type')->default('all');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            // One-off blockings:
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_blockings');
    }
};
