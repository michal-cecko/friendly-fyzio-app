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
        Schema::table('service_categories', function (Blueprint $table) {
            // Drive the default public category page (hero perex + image).
            $table->text('description')->nullable()->after('type');
            $table->string('hero_image')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'hero_image']);
        });
    }
};
