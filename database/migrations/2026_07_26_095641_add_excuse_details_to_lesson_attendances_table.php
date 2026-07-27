<?php

use App\Support\Substitutes\MoveClientToLesson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An absence is more than a missing tick. Staff excusing somebody note down why
 * and who decided it, and once the missed lesson is made up elsewhere the two
 * rows are linked so the presence list can say what replaced what.
 *
 * The replacement link is stored rather than derived: a client-zone redemption
 * could be read back off `substitute_tokens`, but a staff move
 * ({@see MoveClientToLesson}) mints no token at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->string('excuse_reason')->nullable()->after('cancelled_at');
            $table->text('excuse_note')->nullable()->after('excuse_reason');
            $table->foreignUuid('excused_by_id')->nullable()->after('excuse_note')
                ->constrained('users')->nullOnDelete();
            $table->foreignUuid('replacement_attendance_id')->nullable()->after('excused_by_id')
                ->constrained('lesson_attendances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replacement_attendance_id');
            $table->dropConstrainedForeignId('excused_by_id');
            $table->dropColumn(['excuse_reason', 'excuse_note']);
        });
    }
};
