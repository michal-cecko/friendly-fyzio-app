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
        Schema::table('pages', function (Blueprint $table) {
            // Polymorphic owner: a page may be the public page for another model
            // (e.g. a ServiceCategory). UUID morphs because models use HasUuids.
            $table->nullableUuidMorphs('pageable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropMorphs('pageable');
        });
    }
};
