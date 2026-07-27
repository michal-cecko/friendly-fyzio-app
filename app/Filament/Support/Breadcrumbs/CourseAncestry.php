<?php

namespace App\Filament\Support\Breadcrumbs;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the parent chain a course-domain record is reached through, so its
 * breadcrumbs retrace that path instead of pointing back at a flat resource
 * list. A record is always browsed top-down — category → kurz → série → lekce →
 * docházka / přihláška — never through the standalone list of its own kind.
 *
 * Each entry is a `[resource, record, label]` tuple the breadcrumb engine turns
 * into a link (see {@see HasCourseBreadcrumbs}).
 */
class CourseAncestry
{
    /**
     * @return list<array{class-string<\Filament\Resources\Resource>, Model, string}>
     */
    public static function for(Model $record): array
    {
        return match (true) {
            $record instanceof Course => self::forCourse($record),
            $record instanceof CourseSeries => self::forSeries($record),
            $record instanceof CourseEnrollment => self::forEnrollment($record),
            $record instanceof Lesson => self::forLesson($record),
            $record instanceof LessonAttendance => self::forAttendance($record),
            $record instanceof LessonBooking => self::forBooking($record),
            default => [],
        };
    }

    /**
     * @return list<array{class-string<\Filament\Resources\Resource>, Model, string}>
     */
    private static function forCourse(Course $course): array
    {
        $category = $course->category;

        return $category === null
            ? []
            : [[CourseCategoryResource::class, $category, $category->name]];
    }

    /**
     * @return list<array{class-string<\Filament\Resources\Resource>, Model, string}>
     */
    private static function forSeries(CourseSeries $series): array
    {
        $course = $series->course;

        if ($course === null) {
            return [];
        }

        return [
            ...self::forCourse($course),
            [CourseResource::class, $course, $course->name],
        ];
    }

    /**
     * @return list<array{class-string<\Filament\Resources\Resource>, Model, string}>
     */
    private static function forEnrollment(CourseEnrollment $enrollment): array
    {
        $series = $enrollment->series;

        if ($series === null) {
            return [];
        }

        return [
            ...self::forSeries($series),
            [CourseSeriesResource::class, $series, $series->name],
        ];
    }

    /**
     * A lesson hangs off either a course série or a one-off event category,
     * never both; a standalone lesson keeps the default trail.
     *
     * @return list<array{class-string<\Filament\Resources\Resource>, Model, string}>
     */
    private static function forLesson(Lesson $lesson): array
    {
        if (($series = $lesson->series) !== null) {
            return [
                ...self::forSeries($series),
                [CourseSeriesResource::class, $series, $series->name],
            ];
        }

        if (($category = $lesson->category) !== null) {
            return [[EventCategoryResource::class, $category, $category->name]];
        }

        return [];
    }

    /**
     * @return list<array{class-string<\Filament\Resources\Resource>, Model, string}>
     */
    private static function forAttendance(LessonAttendance $attendance): array
    {
        $lesson = $attendance->lesson;

        if ($lesson === null) {
            return [];
        }

        return [
            ...self::forLesson($lesson),
            [LessonResource::class, $lesson, self::lessonCrumb($lesson)],
        ];
    }

    /**
     * Lessons of a série carry no name of their own — under a trail that already
     * names the course and the run, the date is what tells them apart.
     */
    private static function lessonCrumb(Lesson $lesson): string
    {
        return $lesson->name ?? $lesson->startsAt()->format('j. n. Y');
    }

    /**
     * @return list<array{class-string<\Filament\Resources\Resource>, Model, string}>
     */
    private static function forBooking(LessonBooking $booking): array
    {
        $lesson = $booking->lesson;

        if ($lesson === null) {
            return [];
        }

        return [
            ...self::forLesson($lesson),
            [LessonResource::class, $lesson, self::lessonCrumb($lesson)],
        ];
    }
}
