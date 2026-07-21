<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The unified one-off bookable offer replacing the former separate `workshops`
 * and `one_time_lessons` tables. "Workshop" vs. "jednorázová lekce" is now just
 * the event's category; an optional course link powers cross-selling and
 * content fallback (image/description) for course-derived events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_off_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_category_id')->constrained('event_categories')->restrictOnDelete();
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignUuid('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->string('visibility')->default('public');
            $table->string('presale_token')->nullable()->unique();
            $table->string('name');
            $table->string('invoice_title')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('featured_image')->nullable();
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('capacity');
            $table->boolean('auto_promote_waitlist')->default(true);
            $table->integer('price');
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['event_category_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_off_events');
    }
};
