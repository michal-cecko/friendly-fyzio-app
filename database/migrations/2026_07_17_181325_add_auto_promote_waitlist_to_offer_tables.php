<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-offer switch for filling freed spots from the waitlist. Default `true`
 * keeps the existing automatic promotion (docs §4.4); turning it off lets staff
 * promote manually from the waitlist tab instead.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = ['workshops', 'course_series', 'one_time_lessons'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->boolean('auto_promote_waitlist')->default(true)->after('capacity');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('auto_promote_waitlist');
            });
        }
    }
};
