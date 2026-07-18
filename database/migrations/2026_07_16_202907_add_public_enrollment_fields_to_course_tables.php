<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public enrollment surface for courses, one-time lessons and workshops:
 * card/detail images, per-lesson publishing, hidden pre-sale links, client
 * notes on sign-ups and guest-capable waitlist entries (no account needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('featured_image')->nullable();
        });

        Schema::table('workshops', function (Blueprint $table) {
            $table->unsignedBigInteger('featured_image')->nullable();
        });

        Schema::table('one_time_lessons', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
        });

        Schema::table('course_series', function (Blueprint $table) {
            $table->string('presale_token')->nullable()->unique();
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->text('note')->nullable();
        });

        Schema::table('workshop_registrations', function (Blueprint $table) {
            $table->text('note')->nullable();
        });

        Schema::table('one_time_lesson_bookings', function (Blueprint $table) {
            $table->text('note')->nullable();
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreignUuid('client_id')->nullable()->change();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropColumn(['name', 'email', 'phone']);
        });

        Schema::table('one_time_lesson_bookings', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('workshop_registrations', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('course_series', function (Blueprint $table) {
            $table->dropUnique(['presale_token']);
            $table->dropColumn('presale_token');
        });

        Schema::table('one_time_lessons', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });

        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn('featured_image');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('featured_image');
        });
    }
};
