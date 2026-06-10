<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The app's authenticatable model uses UUID primary keys (HasUuids), but the
     * media library's polymorphic `uploader_id` was created as a bigint. Convert
     * it to a UUID column so uploads can persist the uploader morph.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('filament_media_library', 'uploader_id')) {
            return;
        }

        Schema::table('filament_media_library', function (Blueprint $table) {
            $table->dropIndex('fml_uploader_index');
            $table->dropColumn('uploader_id');
        });

        Schema::table('filament_media_library', function (Blueprint $table) {
            $table->uuid('uploader_id')->nullable()->after('uploader_type');
            $table->index(['uploader_type', 'uploader_id'], 'fml_uploader_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('filament_media_library', 'uploader_id')) {
            return;
        }

        Schema::table('filament_media_library', function (Blueprint $table) {
            $table->dropIndex('fml_uploader_index');
            $table->dropColumn('uploader_id');
        });

        Schema::table('filament_media_library', function (Blueprint $table) {
            $table->unsignedBigInteger('uploader_id')->nullable()->after('uploader_type');
            $table->index(['uploader_type', 'uploader_id'], 'fml_uploader_index');
        });
    }
};
