<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('filament_media_library', 'uploader_type')) {
            return;
        }

        try {
            Schema::table('filament_media_library', function (Blueprint $table) {
                $table->dropForeign(['uploaded_by_user_id']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('filament_media_library', function (Blueprint $table) {
            $table->string('uploader_type')->nullable()->after('id');
            $table->uuid('uploader_id')->nullable()->after('uploader_type');
        });

        if (Schema::hasColumn('filament_media_library', 'uploaded_by_user_id')) {
            $authModel = $this->getAuthModel();

            DB::table('filament_media_library')
                ->whereNotNull('uploaded_by_user_id')
                ->update([
                    'uploader_type' => (new $authModel)->getMorphClass(),
                    'uploader_id' => DB::raw('uploaded_by_user_id'),
                ]);

            Schema::table('filament_media_library', function (Blueprint $table) {
                $table->dropColumn(['uploaded_by_user_id']);
            });
        }

        Schema::table('filament_media_library', function (Blueprint $table) {
            $table->index(['uploader_type', 'uploader_id'], 'fml_uploader_index');
        });
    }

    public function down(): void
    {
        // No down() provided due to the conditional up() method...
    }

    protected function getAuthModel(): string
    {
        $panel = Arr::first(
            Filament::getPanels(),
            fn (Panel $panel): bool => $panel->hasPlugin('ralphjsmit/laravel-filament-media-library'),
        );

        if ($panel) {
            return $panel->auth()->getProvider()->getModel();
        }

        $providerName = config('auth.guards.web.provider', 'users');

        return config("auth.providers.{$providerName}.model", 'App\\Models\\User');
    }
};
