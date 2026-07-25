<?php

use App\Enums\WaitlistPromotionMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `auto_promote_waitlist` boolean only knew "next in line gets a real
 * sign-up" vs. "staff do it by hand"; the spec also describes an invite round
 * (§6.4 "Automatický náhradník"), so the flag becomes a three-way mode. The
 * invite window's deadline lives alongside it as `waitlist_invited_until`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['course_series', 'one_off_events'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('waitlist_promotion_mode')
                    ->default(WaitlistPromotionMode::AutomaticAdd->value)
                    ->after('capacity');

                $blueprint->timestamp('waitlist_invited_until')
                    ->nullable()
                    ->after('waitlist_promotion_mode');
            });

            DB::table($table)->where('auto_promote_waitlist', false)->update([
                'waitlist_promotion_mode' => WaitlistPromotionMode::Manual->value,
            ]);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('auto_promote_waitlist');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->boolean('auto_promote_waitlist')->default(true)->after('capacity');
            });

            DB::table($table)
                ->where('waitlist_promotion_mode', WaitlistPromotionMode::Manual->value)
                ->update(['auto_promote_waitlist' => false]);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['waitlist_promotion_mode', 'waitlist_invited_until']);
            });
        }
    }
};
