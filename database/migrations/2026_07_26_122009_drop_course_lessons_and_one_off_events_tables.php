<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Both source tables have been copied into `lessons` and every foreign key and
 * morph alias now points there, so the originals can go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('one_off_events');
    }

    public function down(): void
    {
        // The rows live in `lessons` now; rolling the merge back means restoring
        // from a backup, not recreating empty tables.
    }
};
