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
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->string('link_ref')->nullable()->after('link_type');
        });

        // Upgrade legacy page links to the unified reference shape.
        DB::table('navigation_items')
            ->where('link_type', 'page')
            ->whereNotNull('page_id')
            ->update([
                'link_type' => 'internal',
                'link_ref' => DB::raw("CONCAT('page:', page_id)"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropColumn('link_ref');
        });
    }
};
