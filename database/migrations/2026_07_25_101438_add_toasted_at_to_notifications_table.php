<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when a database notification has already been shown to its recipient as
 * a toast. Without websockets the bell polls, and the poll needs to know which
 * rows it has announced so nothing pops twice. Rows created in a request that
 * already toasted for itself are stamped straight away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('toasted_at')->nullable()->after('read_at');

            $table->index(['notifiable_id', 'toasted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_id', 'toasted_at']);
            $table->dropColumn('toasted_at');
        });
    }
};
