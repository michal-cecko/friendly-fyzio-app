<?php

use App\Enums\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * "Who is in the room" becomes one list. Until now a presence row could only
 * hang off a course enrollment, so somebody who bought a single seat had a
 * purchase (`lesson_bookings`) and no presence at all — they could not be
 * ticked present, could not be excused, and could not even be stopped from
 * booking the same lesson twice (`lesson_bookings` carries no unique index).
 *
 * Purchase and presence are different questions and stay in different tables.
 * What changes is that presence now belongs to the CLIENT, with a nullable link
 * saying how they got there:
 *
 *   enrollment_id set → they are in the course série
 *   booking_id set    → they bought this single lesson
 *
 * The unique key moves with it, to the rule as it was always worded: a client
 * can only be on a lesson's presence list once — whichever way they came.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->foreignUuid('client_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('booking_id')->nullable()->after('enrollment_id')->constrained('lesson_bookings')->cascadeOnDelete();
        });

        $this->backfillClients();

        // A seat bought on its own has no enrollment, so the column has to give
        // way before those rows can be written.
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->dropUnique(['enrollment_id', 'lesson_id']);
            });

            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->uuid('enrollment_id')->nullable()->change();
            });
        });

        $this->seatExistingBookings();

        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->uuid('client_id')->nullable(false)->change();
            });

            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->unique(['client_id', 'lesson_id']);
            });
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            DB::table('lesson_attendances')->whereNull('enrollment_id')->delete();

            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->dropUnique(['client_id', 'lesson_id']);
            });

            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->uuid('enrollment_id')->nullable(false)->change();
            });

            Schema::table('lesson_attendances', function (Blueprint $table) {
                $table->unique(['enrollment_id', 'lesson_id']);
                $table->dropConstrainedForeignId('booking_id');
                $table->dropConstrainedForeignId('client_id');
            });
        });
    }

    /**
     * Every existing row came in through an enrollment, so its client is the
     * one behind that enrollment.
     */
    private function backfillClients(): void
    {
        DB::table('lesson_attendances')
            ->whereNull('client_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $clients = DB::table('course_enrollments')
                    ->whereIn('id', $rows->pluck('enrollment_id')->filter()->unique())
                    ->pluck('client_id', 'id');

                foreach ($rows as $row) {
                    $clientId = $clients[$row->enrollment_id] ?? null;

                    if ($clientId === null) {
                        continue;
                    }

                    DB::table('lesson_attendances')->where('id', $row->id)->update(['client_id' => $clientId]);
                }
            });

        // An enrollment can only vanish by cascade, which takes its rows with
        // it — but never leave a row that cannot name its client.
        DB::table('lesson_attendances')->whereNull('client_id')->delete();
    }

    /**
     * Seats everybody who already holds a booking, so the presence list is
     * complete the moment it becomes the single source.
     */
    private function seatExistingBookings(): void
    {
        $occupying = array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::occupying());
        $now = now();

        DB::table('lesson_bookings')
            ->whereIn('status', $occupying)
            ->orderBy('id')
            ->chunkById(500, function ($bookings) use ($now): void {
                $rows = [];

                foreach ($bookings as $booking) {
                    $taken = DB::table('lesson_attendances')
                        ->where('client_id', $booking->client_id)
                        ->where('lesson_id', $booking->lesson_id)
                        ->exists();

                    if ($taken) {
                        continue;
                    }

                    $rows[] = [
                        'id' => (string) Str::uuid7(),
                        'client_id' => $booking->client_id,
                        'enrollment_id' => null,
                        'booking_id' => $booking->id,
                        'lesson_id' => $booking->lesson_id,
                        'attended' => false,
                        'cancelled_at' => null,
                        'token_generated' => false,
                        'created_at' => $booking->created_at ?? $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('lesson_attendances')->insert($rows);
                }
            });
    }
};
