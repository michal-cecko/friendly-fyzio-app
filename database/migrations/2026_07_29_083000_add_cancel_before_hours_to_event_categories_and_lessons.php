<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One self-cancel window for every event was too blunt: a jednorázová lekce can
 * still be given up two days ahead, a workshop the clinic buys material for
 * needs far more notice. The window therefore moves down to where the types are
 * already told apart — the event category — and a single demanding event can
 * override even that.
 *
 * Null keeps its usual meaning here: inherit. Lesson → category → the
 * `enrollments.event_cancel_before_hours` setting, which stays as the default
 * for categories that never bother to set one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->unsignedInteger('cancel_before_hours')->nullable()->after('display_order');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedInteger('cancel_before_hours')->nullable()->after('price');
        });

        DB::table('settings')->where('key', 'enrollments.event_cancel_before_hours')->update([
            'description' => 'Výchozí lhůta pro samo-odhlášení z jednorázové akce. Kategorie akcí i jednotlivé akce ji mohou přepsat vlastní hodnotou.',
        ]);
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn('cancel_before_hours');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('cancel_before_hours');
        });

        DB::table('settings')->where('key', 'enrollments.event_cancel_before_hours')->update([
            'description' => 'Do kolika hodin před jednorázovou akcí (lekce, workshop…) se klient může sám odhlásit.',
        ]);
    }
};
