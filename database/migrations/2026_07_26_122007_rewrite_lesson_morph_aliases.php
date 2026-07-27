<?php

use App\Enums\PayableType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites every stored reference to the two models that just became one.
 *
 * `OneOffEvent` was in the morph map (`one_off_event`), while `CourseLesson`
 * never was — its rows carry the fully-qualified class name. Both now mean
 * `lesson`, which is also why the permission rename has to cope with a
 * collision: `View:CourseLesson` and `View:OneOffEvent` both want to become
 * `View:Lesson`.
 *
 * This is the one step in the merge that rewrites data rather than structure,
 * so it is not reversible — restore from a backup instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->rewriteMorphs();
            $this->rewriteSettings();
            $this->renamePermissions();
        });
    }

    public function down(): void
    {
        // Data migration — the rewritten aliases have no memory of which of the
        // two models they came from.
    }

    private function rewriteMorphs(): void
    {
        $lesson = ['one_off_event', 'App\Models\OneOffEvent', 'App\Models\CourseLesson', 'course_lesson'];
        $booking = ['one_off_event_booking', 'App\Models\OneOffEventBooking'];

        foreach ([
            ['payments', 'payable_type', $booking, 'lesson_booking'],
            ['invoices', 'invoiceable_type', $booking, 'lesson_booking'],
            ['waitlist_entries', 'waitlistable_type', $lesson, 'lesson'],
            ['reviews', 'reviewable_type', $lesson, 'lesson'],
            ['review_requests', 'reviewable_type', $lesson, 'lesson'],
            ['invitations', 'inviteable_type', $lesson, 'lesson'],
            ['activity_log', 'subject_type', $lesson, 'lesson'],
            ['activity_log', 'subject_type', $booking, 'lesson_booking'],
            ['activity_log', 'causer_type', $lesson, 'lesson'],
            ['activity_log', 'causer_type', $booking, 'lesson_booking'],
        ] as [$table, $column, $from, $to]) {
            DB::table($table)->whereIn($column, $from)->update([$column => $to]);
        }
    }

    /**
     * The invoice item title/description templates are keyed by
     * {@see PayableType}'s value, which changed with the model.
     */
    private function rewriteSettings(): void
    {
        foreach (['item_title', 'item_description'] as $part) {
            DB::table('settings')
                ->where('key', "invoices.{$part}_one_off_event_booking")
                ->update(['key' => "invoices.{$part}_lesson_booking"]);
        }
    }

    /**
     * `:CourseLesson` and `:OneOffEvent` both collapse to `:Lesson`. Renaming
     * the first and deleting the second would throw away whichever roles held
     * only the loser, so the roles are merged onto the survivor first.
     */
    private function renamePermissions(): void
    {
        foreach ([':OneOffEventBooking' => ':LessonBooking', ':OneOffEvent' => ':Lesson', ':CourseLesson' => ':Lesson'] as $from => $to) {
            foreach (DB::table('permissions')->where('name', 'like', '%'.$from)->get(['id', 'name', 'guard_name']) as $permission) {
                $name = str_replace($from, $to, $permission->name);

                $survivor = DB::table('permissions')
                    ->where('name', $name)
                    ->where('guard_name', $permission->guard_name)
                    ->first(['id']);

                if ($survivor === null) {
                    DB::table('permissions')->where('id', $permission->id)->update(['name' => $name]);

                    continue;
                }

                $this->mergeInto($permission->id, $survivor->id);
            }
        }

        cache()->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    /**
     * Moves every role/model assignment from a duplicate permission onto the
     * one that survives, then deletes the duplicate.
     */
    private function mergeInto(int|string $duplicateId, int|string $survivorId): void
    {
        foreach ([
            ['role_has_permissions', 'role_id'],
            ['model_has_permissions', 'model_id'],
        ] as [$table, $ownerColumn]) {
            $held = DB::table($table)->where('permission_id', $survivorId)->pluck($ownerColumn)->all();

            DB::table($table)
                ->where('permission_id', $duplicateId)
                ->whereIn($ownerColumn, $held)
                ->delete();

            DB::table($table)
                ->where('permission_id', $duplicateId)
                ->update(['permission_id' => $survivorId]);
        }

        DB::table('permissions')->where('id', $duplicateId)->delete();
    }
};
