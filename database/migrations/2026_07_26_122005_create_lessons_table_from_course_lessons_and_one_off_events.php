<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A lesson of a course série and a standalone jednorázová lekce are the same
 * thing seen from two sides: a session, in a room, with an instructor, at a
 * time. The only real difference is whether it belongs to a série and whether
 * it is on public sale — so they become one row rather than two.
 *
 *   series_id set,  published_at null → a lesson of a course série (schedule)
 *   series_id set,  published_at set  → that same lesson, also sold as a drop-in
 *   series_id null, published_at set  → a standalone workshop / jednorázová lekce
 *
 * One row means one occupancy number: the double-booking that came from
 * `CourseLesson::takenSpots()` and `HasCapacity::takenSpots()` never seeing each
 * other cannot happen any more.
 *
 * UUIDs are copied verbatim from both source tables, so every foreign key value
 * survives and the follow-up migration only has to repoint the constraint. Raw
 * DB::table() throughout so it behaves identically on PostgreSQL and SQLite —
 * the same approach the 2026-07-18 workshop/one-time-lesson merge used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Set = a lesson of a course série; null = a standalone offer.
            $table->foreignUuid('series_id')->nullable()->constrained('course_series')->nullOnDelete();
            // Needed only once the lesson is public — it forms the URL.
            $table->foreignUuid('event_category_id')->nullable()->constrained('event_categories')->restrictOnDelete();
            // Cross-sell + image/description fallback for course-derived offers.
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignUuid('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();

            $table->date('lesson_date');
            $table->time('start_time');
            $table->time('end_time');

            $table->string('name')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('invoice_title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('featured_image')->nullable();

            // Null = borrow the série's capacity / the course's drop-in price.
            $table->integer('capacity')->nullable();
            $table->integer('price')->nullable();

            $table->string('visibility')->default('public');
            $table->string('presale_token')->nullable()->unique();
            $table->timestamp('published_at')->nullable();
            // When a free place went on public sale; null = never released.
            $table->timestamp('released_at')->nullable();

            $table->string('waitlist_promotion_mode')->nullable();
            $table->timestamp('waitlist_invited_until')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['series_id', 'lesson_date']);
            $table->index(['event_category_id', 'lesson_date']);
        });

        DB::transaction(function (): void {
            $this->copyOneOffEvents();
            $this->copyCourseLessons();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }

    /**
     * Straight column map; `event_date` becomes `lesson_date`. Standalone offers
     * keep their own capacity and price.
     */
    private function copyOneOffEvents(): void
    {
        DB::table('one_off_events')->orderBy('id')->chunk(200, function ($events): void {
            DB::table('lessons')->insert($events->map(fn ($event): array => [
                'id' => $event->id,
                'series_id' => null,
                'event_category_id' => $event->event_category_id,
                'course_id' => $event->course_id,
                'instructor_id' => $event->instructor_id,
                'room_id' => $event->room_id,
                'lesson_date' => $event->event_date,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'name' => $event->name,
                'slug' => $event->slug,
                'invoice_title' => $event->invoice_title,
                'description' => $event->description,
                'featured_image' => $event->featured_image,
                'capacity' => $event->capacity,
                'price' => $event->price,
                'visibility' => $event->visibility,
                'presale_token' => $event->presale_token,
                'published_at' => $event->published_at,
                'released_at' => null,
                'waitlist_promotion_mode' => $event->waitlist_promotion_mode,
                'waitlist_invited_until' => $event->waitlist_invited_until,
                'deleted_at' => $event->deleted_at,
                'created_at' => $event->created_at,
                'updated_at' => $event->updated_at,
            ])->all());
        });
    }

    /**
     * Course lessons carry only the seven scheduling columns; everything that
     * makes a lesson sellable stays null until it is released.
     */
    private function copyCourseLessons(): void
    {
        DB::table('course_lessons')->orderBy('id')->chunk(200, function ($lessons): void {
            DB::table('lessons')->insert($lessons->map(fn ($lesson): array => [
                'id' => $lesson->id,
                'series_id' => $lesson->series_id,
                'event_category_id' => null,
                'course_id' => null,
                'instructor_id' => $lesson->instructor_id,
                'room_id' => $lesson->room_id,
                'lesson_date' => $lesson->lesson_date,
                'start_time' => $lesson->start_time,
                'end_time' => $lesson->end_time,
                'name' => null,
                'slug' => null,
                'invoice_title' => null,
                'description' => null,
                'featured_image' => null,
                'capacity' => null,
                'price' => null,
                'visibility' => 'public',
                'presale_token' => null,
                'published_at' => null,
                'released_at' => null,
                'waitlist_promotion_mode' => null,
                'waitlist_invited_until' => null,
                'deleted_at' => null,
                'created_at' => $lesson->created_at,
                'updated_at' => $lesson->updated_at,
            ])->all());
        });
    }
};
