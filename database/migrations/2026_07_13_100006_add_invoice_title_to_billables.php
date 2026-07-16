<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-entity override of how the sold item is titled on invoices
     * and in payment e-mails ("Název pro fakturaci"); falls back to `name`.
     */
    public function up(): void
    {
        foreach (['services', 'workshops', 'one_time_lessons', 'course_series'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('invoice_title')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['services', 'workshops', 'one_time_lessons', 'course_series'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('invoice_title');
            });
        }
    }
};
