<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The card photo is landscape and the detail photo square, so they are two
 * separate columns. Either one alone still has to fill both surfaces, and a
 * lesson keeps inheriting from its course when it has no photos of its own.
 */
class OfferImageFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_with_both_photos_uses_each_where_it_belongs(): void
    {
        $course = Course::factory()->create([
            'featured_image' => 11,
            'detail_image' => 22,
        ]);

        $this->assertSame(11, $course->cardImage());
        $this->assertSame(22, $course->detailImage());
    }

    public function test_course_with_only_a_card_photo_reuses_it_in_the_detail(): void
    {
        $course = Course::factory()->create([
            'featured_image' => 11,
            'detail_image' => null,
        ]);

        $this->assertSame(11, $course->cardImage());
        $this->assertSame(11, $course->detailImage());
    }

    public function test_course_with_only_a_detail_photo_reuses_it_on_the_card(): void
    {
        $course = Course::factory()->create([
            'featured_image' => null,
            'detail_image' => 22,
        ]);

        $this->assertSame(22, $course->cardImage());
        $this->assertSame(22, $course->detailImage());
    }

    public function test_course_without_photos_has_none(): void
    {
        $course = Course::factory()->create([
            'featured_image' => null,
            'detail_image' => null,
        ]);

        $this->assertNull($course->cardImage());
        $this->assertNull($course->detailImage());
    }

    public function test_lesson_prefers_its_own_photos_over_the_course_ones(): void
    {
        $course = Course::factory()->create([
            'featured_image' => 11,
            'detail_image' => 22,
        ]);

        $lesson = Lesson::factory()->create([
            'series_id' => CourseSeries::factory()->for($course),
            'featured_image' => 33,
            'detail_image' => 44,
        ]);

        $this->assertSame(33, $lesson->displayCardImage());
        $this->assertSame(44, $lesson->displayDetailImage());
    }

    public function test_lesson_with_a_single_own_photo_uses_it_on_both_surfaces(): void
    {
        $course = Course::factory()->create([
            'featured_image' => 11,
            'detail_image' => 22,
        ]);

        $lesson = Lesson::factory()->create([
            'series_id' => CourseSeries::factory()->for($course),
            'featured_image' => null,
            'detail_image' => 44,
        ]);

        $this->assertSame(44, $lesson->displayCardImage());
        $this->assertSame(44, $lesson->displayDetailImage());
    }

    public function test_lesson_without_photos_inherits_both_from_its_course(): void
    {
        $course = Course::factory()->create([
            'featured_image' => 11,
            'detail_image' => 22,
        ]);

        $lesson = Lesson::factory()->create([
            'series_id' => CourseSeries::factory()->for($course),
            'featured_image' => null,
            'detail_image' => null,
        ]);

        $this->assertSame(11, $lesson->displayCardImage());
        $this->assertSame(22, $lesson->displayDetailImage());
    }

    public function test_lesson_inherits_through_the_courses_own_fallback(): void
    {
        $course = Course::factory()->create([
            'featured_image' => null,
            'detail_image' => 22,
        ]);

        $lesson = Lesson::factory()->create([
            'series_id' => CourseSeries::factory()->for($course),
            'featured_image' => null,
            'detail_image' => null,
        ]);

        $this->assertSame(22, $lesson->displayCardImage());
        $this->assertSame(22, $lesson->displayDetailImage());
    }

    public function test_standalone_lesson_inherits_from_the_cross_sell_course(): void
    {
        $course = Course::factory()->create([
            'featured_image' => 11,
            'detail_image' => 22,
        ]);

        $lesson = Lesson::factory()->standalone()->withCourse($course)->create([
            'featured_image' => null,
            'detail_image' => null,
        ]);

        $this->assertSame(11, $lesson->displayCardImage());
        $this->assertSame(22, $lesson->displayDetailImage());
    }

    public function test_standalone_lesson_without_a_course_or_photos_has_none(): void
    {
        $lesson = Lesson::factory()->standalone()->create([
            'featured_image' => null,
            'detail_image' => null,
        ]);

        $this->assertNull($lesson->displayCardImage());
        $this->assertNull($lesson->displayDetailImage());
    }
}
