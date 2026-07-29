<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the single offer photo in two: `featured_image` keeps the landscape
 * card photo, `detail_image` holds the square one for the detail hero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('detail_image')->nullable()->after('featured_image');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedBigInteger('detail_image')->nullable()->after('featured_image');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('detail_image');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('detail_image');
        });
    }
};
