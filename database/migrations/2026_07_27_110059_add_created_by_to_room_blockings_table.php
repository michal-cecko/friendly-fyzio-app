<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A blocking has no therapist of its own, so its owner is whoever put it on the
 * grid: staff scoped to their own work may edit or delete only the blockings
 * they created. Rows without a creator — imports, seeds, anything an
 * administrator added — stay read-only for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_blockings', function (Blueprint $table) {
            $table->foreignUuid('created_by')->nullable()->after('room_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_blockings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
