<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->string('placement')->default('all');
            $table->json('page_ids')->nullable();
            $table->json('content')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_to')->nullable();
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
