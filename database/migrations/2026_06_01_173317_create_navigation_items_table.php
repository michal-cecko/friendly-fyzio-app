<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('navigation_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('label');
            $table->string('url')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('navigation_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
