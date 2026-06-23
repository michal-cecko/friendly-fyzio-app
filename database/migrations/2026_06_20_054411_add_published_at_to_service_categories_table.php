<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Gates the public category page (null/future = hidden, staff-previewable).
        Schema::table('service_categories', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('hero_image');
        });

        // Keep existing categories publicly visible.
        DB::table('service_categories')->whereNull('published_at')->update(['published_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
