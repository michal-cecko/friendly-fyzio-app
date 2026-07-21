<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data migration for the workshop + one-time-lesson unification: copies both
 * offer tables (and their booking tables) into the new one_off_events /
 * one_off_event_bookings tables PRESERVING UUIDs, then rewrites every stored
 * morph alias, e-mail template key, settings key, page brick config, navigation
 * link ref and permission name to the unified names. The old tables are left in
 * place (dropped by a later cleanup migration).
 *
 * Everything is raw DB::table() + PHP-side JSON so it runs identically on
 * PostgreSQL (dev) and SQLite (tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            [$workshopyId, $lekceId] = $this->ensureCategories();

            $this->copyWorkshops($workshopyId);
            $this->copyLessons($lekceId);
            $this->copyBookings();

            $this->rewriteMorphs();
            $this->mergeEmailTemplates();
            $this->rewriteSettings();
            $this->rewritePageBricks();
            $this->attachWorkshopsPage($workshopyId);
            $this->rewriteNavigationRefs();
            $this->renamePermissions();
        });
    }

    public function down(): void
    {
        // Data migration — not reversible (the copied rows and rewritten
        // aliases have no memory of their origin). Restore from backup instead.
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function ensureCategories(): array
    {
        $ids = [];

        foreach ([
            ['slug' => 'workshopy', 'name' => 'Workshopy', 'display_order' => 1],
            ['slug' => 'jednorazove-lekce', 'name' => 'Jednorázové lekce', 'display_order' => 2],
        ] as $category) {
            $existing = DB::table('event_categories')->where('slug', $category['slug'])->first();

            if ($existing !== null) {
                $ids[] = $existing->id;

                continue;
            }

            $id = (string) Str::uuid7();

            DB::table('event_categories')->insert([
                'id' => $id,
                'name' => $category['name'],
                'slug' => $category['slug'],
                'display_order' => $category['display_order'],
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ids[] = $id;
        }

        return [$ids[0], $ids[1]];
    }

    private function copyWorkshops(string $workshopyId): void
    {
        DB::table('workshops')->orderBy('id')->chunk(200, function ($workshops) use ($workshopyId): void {
            $rows = $workshops->map(fn ($workshop): array => [
                'id' => $workshop->id,
                'event_category_id' => $workshopyId,
                'course_id' => null,
                'instructor_id' => $workshop->instructor_id,
                'room_id' => $workshop->room_id,
                'visibility' => $workshop->visibility,
                'presale_token' => $workshop->presale_token,
                'name' => $workshop->name,
                'invoice_title' => $workshop->invoice_title,
                'slug' => $workshop->slug,
                'description' => $workshop->description,
                'featured_image' => $workshop->featured_image,
                'event_date' => $workshop->workshop_date,
                'start_time' => $workshop->start_time,
                'end_time' => $workshop->end_time,
                'capacity' => $workshop->capacity,
                'auto_promote_waitlist' => $workshop->auto_promote_waitlist,
                'price' => $workshop->price,
                'published_at' => $workshop->published_at,
                'deleted_at' => $workshop->deleted_at,
                'created_at' => $workshop->created_at,
                'updated_at' => $workshop->updated_at,
            ])->all();

            DB::table('one_off_events')->insert($rows);
        });
    }

    private function copyLessons(string $lekceId): void
    {
        $usedSlugs = DB::table('one_off_events')->pluck('slug')->flip()->all();

        DB::table('one_time_lessons')
            ->leftJoin('courses', 'courses.id', '=', 'one_time_lessons.course_id')
            ->orderBy('one_time_lessons.id')
            ->select('one_time_lessons.*', 'courses.name as course_name', 'courses.slug as course_slug')
            ->chunk(200, function ($lessons) use ($lekceId, &$usedSlugs): void {
                $rows = [];

                foreach ($lessons as $lesson) {
                    $courseName = $lesson->course_name ?? 'Lekce';
                    $date = Carbon::parse($lesson->lesson_date);

                    $slug = Str::slug(($lesson->course_slug ?? 'lekce').'-'.$date->format('Y-m-d'));
                    $candidate = $slug;
                    $suffix = 2;

                    while (isset($usedSlugs[$candidate])) {
                        $candidate = $slug.'-'.$suffix++;
                    }

                    $usedSlugs[$candidate] = true;

                    $rows[] = [
                        'id' => $lesson->id,
                        'event_category_id' => $lekceId,
                        'course_id' => $lesson->course_id,
                        'instructor_id' => $lesson->instructor_id,
                        'room_id' => $lesson->room_id,
                        'visibility' => $lesson->visibility,
                        'presale_token' => $lesson->presale_token,
                        'name' => $courseName.' – jednorázová lekce',
                        'invoice_title' => $lesson->invoice_title,
                        'slug' => $candidate,
                        'description' => null,
                        'featured_image' => null,
                        'event_date' => $lesson->lesson_date,
                        'start_time' => $lesson->start_time,
                        'end_time' => $lesson->end_time,
                        'capacity' => $lesson->capacity,
                        'auto_promote_waitlist' => $lesson->auto_promote_waitlist,
                        'price' => $lesson->price,
                        'published_at' => $lesson->published_at,
                        'deleted_at' => null,
                        'created_at' => $lesson->created_at,
                        'updated_at' => $lesson->updated_at,
                    ];
                }

                DB::table('one_off_events')->insert($rows);
            });
    }

    private function copyBookings(): void
    {
        DB::table('workshop_registrations')->orderBy('id')->chunk(500, function ($registrations): void {
            DB::table('one_off_event_bookings')->insert(
                $registrations->map(fn ($registration): array => [
                    'id' => $registration->id,
                    'client_id' => $registration->client_id,
                    'one_off_event_id' => $registration->workshop_id,
                    'status' => $registration->status,
                    'payment_status' => $registration->payment_status,
                    'paid_at' => $registration->paid_at,
                    'note' => $registration->note,
                    'created_at' => $registration->created_at,
                    'updated_at' => $registration->updated_at,
                ])->all(),
            );
        });

        DB::table('one_time_lesson_bookings')->orderBy('id')->chunk(500, function ($bookings): void {
            DB::table('one_off_event_bookings')->insert(
                $bookings->map(fn ($booking): array => [
                    'id' => $booking->id,
                    'client_id' => $booking->client_id,
                    'one_off_event_id' => $booking->lesson_id,
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'paid_at' => $booking->paid_at,
                    'note' => $booking->note,
                    'created_at' => $booking->created_at,
                    'updated_at' => $booking->updated_at,
                ])->all(),
            );
        });
    }

    /**
     * Rewrite every stored morph alias — ids were preserved, so only the type
     * strings change.
     */
    private function rewriteMorphs(): void
    {
        $offerAliases = ['workshop', 'one_time_lesson'];
        $bookingAliases = ['workshop_registration', 'one_time_lesson_booking'];

        foreach ([
            ['payments', 'payable_type', $bookingAliases, 'one_off_event_booking'],
            ['invoices', 'invoiceable_type', $bookingAliases, 'one_off_event_booking'],
            ['waitlist_entries', 'waitlistable_type', $offerAliases, 'one_off_event'],
            ['reviews', 'reviewable_type', ['workshop'], 'one_off_event'],
            ['review_requests', 'reviewable_type', $offerAliases, 'one_off_event'],
            ['invitations', 'inviteable_type', $offerAliases, 'one_off_event'],
            ['activity_log', 'subject_type', $offerAliases, 'one_off_event'],
            ['activity_log', 'subject_type', $bookingAliases, 'one_off_event_booking'],
            ['activity_log', 'causer_type', $offerAliases, 'one_off_event'],
            ['activity_log', 'causer_type', $bookingAliases, 'one_off_event_booking'],
        ] as [$table, $column, $from, $to]) {
            DB::table($table)->whereIn($column, $from)->update([$column => $to]);
        }
    }

    /**
     * The two "sign-up received" templates collapse into one event template:
     * the workshop row survives under the new key (tokens rewritten), the
     * lesson row is dropped.
     */
    private function mergeEmailTemplates(): void
    {
        $workshopRow = DB::table('email_templates')->where('key', 'workshop_registration_received')->first();

        if ($workshopRow !== null) {
            $subject = $this->rewriteTokens((string) $workshopRow->subject);

            if ($subject === 'Přijali jsme vaši registraci na workshop') {
                $subject = 'Přijali jsme vaši přihlášku';
            }

            DB::table('email_templates')->where('id', $workshopRow->id)->update([
                'key' => 'event_booking_received',
                'name' => 'Přihláška na jednorázovou akci přijata',
                'subject' => $subject,
                'content' => $this->rewriteTokens((string) $workshopRow->content),
            ]);
        }

        DB::table('email_templates')->where('key', 'lesson_booking_received')->delete();
    }

    private function rewriteSettings(): void
    {
        // One unified self-cancel window replaces the per-type lesson/workshop pair
        // (seeded from the lesson value, which was the tighter hours-based one).
        DB::table('settings')->where('key', 'enrollments.lesson_cancel_before_hours')->update([
            'key' => 'enrollments.event_cancel_before_hours',
            'label' => 'Odhlášení z akce (hodin předem)',
            'description' => 'Do kolika hodin před jednorázovou akcí (lekce, workshop…) se klient může sám odhlásit.',
        ]);
        DB::table('settings')->where('key', 'enrollments.workshop_cancel_before_days')->delete();

        // Invoice item templates: keep the workshop pair under the unified key.
        foreach (['item_title', 'item_description'] as $kind) {
            $workshopKey = "invoices.{$kind}_workshop_registration";
            $lessonKey = "invoices.{$kind}_one_time_lesson_booking";
            $newKey = "invoices.{$kind}_one_off_event_booking";

            $row = DB::table('settings')->where('key', $workshopKey)->first()
                ?? DB::table('settings')->where('key', $lessonKey)->first();

            if ($row !== null) {
                DB::table('settings')->where('id', $row->id)->update([
                    'key' => $newKey,
                    'value' => $row->value === null ? null : $this->rewriteTokens((string) $row->value),
                ]);
            }

            DB::table('settings')->whereIn('key', [$workshopKey, $lessonKey])->delete();
        }
    }

    private function rewritePageBricks(): void
    {
        foreach (DB::table('pages')->whereNotNull('content')->get(['id', 'content']) as $page) {
            $content = json_decode((string) $page->content, true);

            if (! is_array($content)) {
                continue;
            }

            $changed = false;

            foreach ($content as &$node) {
                $id = $node['attrs']['id'] ?? null;
                $config = $node['attrs']['config'] ?? [];

                if ($id === 'workshop-archive') {
                    $node['attrs']['id'] = 'event-archive';
                    $node['attrs']['config']['category'] = 'workshopy';
                    $changed = true;
                }

                if ($id === 'course-archive') {
                    unset($node['attrs']['config']['show_type_switch']);
                    $node['attrs']['config'] += [
                        'cross_sell' => true,
                        'cross_sell_title' => 'Chcete si to nejdřív vyzkoušet?',
                        'cross_sell_text' => 'Přijďte na jednorázovou lekci bez závazku celého kurzu.',
                        'cross_sell_category' => 'jednorazove-lekce',
                    ];
                    $changed = true;
                }

                if (($config['reviewable_type'] ?? null) === 'workshop') {
                    $node['attrs']['config']['reviewable_type'] = 'one_off_event';
                    $changed = true;
                }
            }
            unset($node);

            if ($changed) {
                DB::table('pages')->where('id', $page->id)->update([
                    'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }

    /**
     * The seeded /workshopy archive page becomes the Workshopy category's
     * custom page — same URL, now served through EventCategoryController.
     */
    private function attachWorkshopsPage(string $workshopyId): void
    {
        DB::table('pages')->where('system_key', 'workshopy')->update([
            'pageable_type' => 'event_category',
            'pageable_id' => $workshopyId,
        ]);
    }

    private function rewriteNavigationRefs(): void
    {
        foreach (DB::table('navigation_items')
            ->where(fn ($query) => $query
                ->where('link_ref', 'like', 'workshop:%')
                ->orWhere('link_ref', 'like', 'lesson:%'))
            ->get(['id', 'link_ref']) as $item) {
            DB::table('navigation_items')->where('id', $item->id)->update([
                'link_ref' => 'event:'.Str::after($item->link_ref, ':'),
            ]);
        }
    }

    /**
     * Shield permission rows: rename the workshop pair (keeps role links),
     * drop the lesson pair (covered by the renamed rows), and clone the
     * CourseCategory permission set for EventCategory.
     */
    private function renamePermissions(): void
    {
        foreach ([
            ':Workshop' => ':OneOffEvent',
            ':WorkshopRegistration' => ':OneOffEventBooking',
        ] as $from => $to) {
            foreach (DB::table('permissions')->where('name', 'like', '%'.$from)->get(['id', 'name']) as $permission) {
                DB::table('permissions')->where('id', $permission->id)->update([
                    'name' => str_replace($from, $to, $permission->name),
                ]);
            }
        }

        $obsolete = DB::table('permissions')
            ->where('name', 'like', '%:OneTimeLesson')
            ->orWhere('name', 'like', '%:OneTimeLessonBooking')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $obsolete)->delete();
        DB::table('permissions')->whereIn('id', $obsolete)->delete();

        foreach (DB::table('permissions')->where('name', 'like', '%:CourseCategory')->get() as $template) {
            $name = str_replace(':CourseCategory', ':EventCategory', $template->name);

            if (DB::table('permissions')->where('name', $name)->where('guard_name', $template->guard_name)->exists()) {
                continue;
            }

            $newId = DB::table('permissions')->insertGetId([
                'name' => $name,
                'guard_name' => $template->guard_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $links = DB::table('role_has_permissions')->where('permission_id', $template->id)->pluck('role_id');

            foreach ($links as $roleId) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $newId,
                    'role_id' => $roleId,
                ]);
            }
        }

        cache()->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    /**
     * The old per-type name tokens become the unified {{ nazev }}.
     */
    private function rewriteTokens(string $value): string
    {
        return str_replace(
            ['{{ workshop }}', '{{workshop}}', '{{ lekce }}', '{{lekce}}'],
            ['{{ nazev }}', '{{ nazev }}', '{{ nazev }}', '{{ nazev }}'],
            $value,
        );
    }
};
