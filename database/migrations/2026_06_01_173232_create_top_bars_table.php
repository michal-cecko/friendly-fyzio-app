<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('top_bars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('content');
            $table->string('link_url')->nullable();
            $table->string('background_color')->nullable();
            $table->boolean('visible')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_bars');
    }
};
