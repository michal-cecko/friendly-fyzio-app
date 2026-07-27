<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a single lesson of this course costs when somebody buys just that one —
 * a place freed by an excuse, or a seat an under-booked série never sold.
 *
 * Null means this course's lessons are never sold individually, which is the
 * safe default for every existing course: nothing goes on public sale until
 * somebody prices it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->integer('drop_in_price')->nullable()->after('early_cancel_hours');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('drop_in_price');
        });
    }
};
