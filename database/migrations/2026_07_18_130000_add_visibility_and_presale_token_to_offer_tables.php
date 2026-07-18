<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give one-time lessons and workshops the same private-visibility + hidden
     * pre-sale link mechanism course series already have.
     */
    public function up(): void
    {
        foreach (['one_time_lessons', 'workshops'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('visibility')->default('public')->after('room_id');
                $table->string('presale_token')->nullable()->unique()->after('visibility');
            });
        }
    }

    public function down(): void
    {
        foreach (['one_time_lessons', 'workshops'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['visibility', 'presale_token']);
            });
        }
    }
};
