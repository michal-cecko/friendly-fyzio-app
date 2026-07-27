<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The break after a visit belongs to the therapist, not to the service — it is
 * their rest after treating a client, so two therapists doing the same massage
 * may well need different amounts of it (FSS :189, „prestávka per služba per
 * terapeut").
 *
 * So `services.break_minutes` gives way to a default on the staff profile, which
 * an individual service assignment may override:
 *
 *   service_therapists.break_blocks ?? staff_profiles.break_blocks
 *
 * Both are counted in reservation blocks rather than minutes, because the whole
 * scheduling model is („Systém 15-minútových blokov"); one block is whatever
 * `reservation.block_minutes` currently says.
 *
 * Every existing assignment inherits the break its service carried, so the slot
 * engine offers exactly the same times the day this runs; the profile default
 * only governs assignments made from here on. Reservations additionally freeze
 * the break they were booked with, so a therapist changing their default never
 * silently reshapes bookings that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $block = $this->blockMinutes();

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->unsignedInteger('break_blocks')->default(1)->after('display_order');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('break_minutes')->default(0)->after('end_time');
        });

        $this->giveThePivotItsOwnKey();
        $this->backfillBreaks($block);

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('break_minutes');
        });
    }

    public function down(): void
    {
        $block = $this->blockMinutes();

        Schema::table('services', function (Blueprint $table) {
            $table->integer('break_minutes')->default(0)->after('price');
        });

        $this->restoreServiceBreaks($block);

        Schema::table('service_therapists', function (Blueprint $table) {
            $table->dropUnique(['service_id', 'therapist_id']);
        });

        Schema::table('service_therapists', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('service_therapists', function (Blueprint $table) {
            $table->dropColumn(['id', 'break_blocks']);
        });

        Schema::table('service_therapists', function (Blueprint $table) {
            $table->primary(['service_id', 'therapist_id']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('break_minutes');
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn('break_blocks');
        });
    }

    /**
     * The pivot was keyed on its two foreign keys, which leaves nothing for a
     * repeater row (or anything else) to address. It gets a surrogate id; the
     * old composite key lives on as a unique index, so a therapist still cannot
     * be assigned to the same service twice.
     */
    private function giveThePivotItsOwnKey(): void
    {
        Schema::table('service_therapists', function (Blueprint $table) {
            $table->dropPrimary(['service_id', 'therapist_id']);
        });

        Schema::table('service_therapists', function (Blueprint $table) {
            $table->uuid('id')->nullable()->first();
            $table->unsignedInteger('break_blocks')->nullable();
            $table->unique(['service_id', 'therapist_id']);
        });

        foreach (DB::table('service_therapists')->get() as $row) {
            DB::table('service_therapists')
                ->where('service_id', $row->service_id)
                ->where('therapist_id', $row->therapist_id)
                ->update(['id' => (string) Str::uuid7()]);
        }

        Schema::table('service_therapists', function (Blueprint $table) {
            $table->uuid('id')->nullable(false)->change();
        });

        Schema::table('service_therapists', function (Blueprint $table) {
            $table->primary('id');
        });
    }

    /**
     * Carry each service's break onto its assignments (and onto the bookings
     * that already used it) before the column disappears.
     */
    private function backfillBreaks(int $block): void
    {
        $byMinutes = DB::table('services')
            ->select('id', 'break_minutes')
            ->get()
            ->groupBy('break_minutes');

        foreach ($byMinutes as $minutes => $services) {
            $ids = $services->pluck('id');

            DB::table('service_therapists')
                ->whereIn('service_id', $ids)
                ->update(['break_blocks' => (int) round(((int) $minutes) / $block)]);

            DB::table('reservations')
                ->whereIn('service_id', $ids)
                ->update(['break_minutes' => (int) $minutes]);
        }
    }

    /**
     * Collapsing back to one value per service is lossy by definition — the
     * longest break any of its therapists takes is the safe choice, since it is
     * the only one that cannot double-book anybody.
     */
    private function restoreServiceBreaks(int $block): void
    {
        $rows = DB::table('service_therapists')
            ->join('staff_profiles', 'staff_profiles.id', '=', 'service_therapists.therapist_id')
            ->select(
                'service_therapists.service_id',
                DB::raw('max(coalesce(service_therapists.break_blocks, staff_profiles.break_blocks)) as blocks'),
            )
            ->groupBy('service_therapists.service_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('services')
                ->where('id', $row->service_id)
                ->update(['break_minutes' => ((int) $row->blocks) * $block]);
        }
    }

    /**
     * Read straight from the settings table rather than through the cached
     * accessor, so the migration is not at the mercy of a warm settings cache.
     */
    private function blockMinutes(): int
    {
        $value = DB::table('settings')->where('key', 'reservation.block_minutes')->value('value');

        return max(1, (int) ($value ?: 15));
    }
};
