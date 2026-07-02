<?php

use App\Models\Page;
use App\Support\BrickDataMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Folds the legacy per-brick button/link fields (button_text/button_url,
 * cards' link_text, category-cards inner urls/string items) into the unified
 * button shape. Idempotent via App\Support\BrickDataMigrator; not reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            Page::withTrashed()->each(function (Page $page): void {
                $content = $page->content;

                if (! is_array($content) || $content === []) {
                    return;
                }

                $migrated = BrickDataMigrator::migrateContent($content);

                if ($migrated !== $content) {
                    $page->content = $migrated;
                    $page->saveQuietly();
                }
            });
        });
    }

    public function down(): void
    {
        // Not reversible — legacy keys are dropped during the transform.
    }
};
