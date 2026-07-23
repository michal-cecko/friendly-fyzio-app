<?php

namespace Tests\Feature\Cms;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Mason\Bricks\EnrollingNowBrick;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSeries;
use App\Support\Enrollments\EnrollingNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EnrollingNowBrickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 1, 9, 0));
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function publishedCategory(array $attributes = []): CourseCategory
    {
        return CourseCategory::factory()->create([
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    private function publishedCourse(CourseCategory $category, array $attributes = []): Course
    {
        return Course::factory()->create([
            'category_id' => $category->id,
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    private function series(Course $course, array $attributes = []): CourseSeries
    {
        return CourseSeries::factory()->create([
            'course_id' => $course->id,
            'status' => CourseSeriesStatus::Open,
            'visibility' => CourseSeriesVisibility::Public,
            'start_date' => '2026-07-01',
            'end_date' => '2026-09-01',
            ...$attributes,
        ]);
    }

    public function test_surfaces_a_category_with_an_open_course_and_deep_links_both(): void
    {
        $category = $this->publishedCategory(['name' => 'Jóga', 'slug' => 'joga']);
        $course = $this->publishedCourse($category, ['name' => 'Hormonální jóga']);
        $this->series($course);

        $cards = EnrollingNow::compute();

        $this->assertCount(1, $cards);
        $card = $cards[0];
        $this->assertSame('Jóga', $card['title']);
        $this->assertSame('1 otevřený kurz', $card['subtitle']);
        $this->assertStringEndsWith('/kurzy?kategorie=joga', $card['url']);
        $this->assertSame($course->permalink(), $card['items'][0]['url']);
        $this->assertSame('Začíná 1. 7. 2026', $card['items'][0]['meta']);
    }

    public function test_counts_open_courses_with_czech_plural(): void
    {
        $category = $this->publishedCategory();
        foreach (['Alfa', 'Beta', 'Gama'] as $name) {
            $this->series($this->publishedCourse($category, ['name' => $name]));
        }

        $card = EnrollingNow::compute()[0];

        $this->assertSame('3 otevřené kurzy', $card['subtitle']);
        $this->assertSame(['Alfa', 'Beta', 'Gama'], array_column($card['items'], 'label'));
    }

    public function test_run_already_underway_is_labelled_probiha(): void
    {
        $category = $this->publishedCategory();
        $course = $this->publishedCourse($category);
        $this->series($course, ['start_date' => '2026-05-01', 'end_date' => '2026-08-01']);

        $this->assertSame('Probíhá', EnrollingNow::compute()[0]['items'][0]['meta']);
    }

    public function test_category_whose_runs_are_all_disqualified_is_excluded(): void
    {
        $category = $this->publishedCategory();

        // Full run (waitlist only, not actively enrolling).
        $this->series($this->publishedCourse($category), ['status' => CourseSeriesStatus::Full]);
        // Inactive run.
        $this->series($this->publishedCourse($category), ['status' => CourseSeriesStatus::Inactive]);
        // Private (invite-only) run.
        $this->series($this->publishedCourse($category), ['visibility' => CourseSeriesVisibility::Private]);
        // Open + public but already ended.
        $this->series($this->publishedCourse($category), ['start_date' => '2026-01-01', 'end_date' => '2026-05-15']);
        // Open + public + current, but on an unpublished course.
        $this->series($this->publishedCourse($category, ['published_at' => null]));

        $this->assertSame([], EnrollingNow::compute());
    }

    public function test_unpublished_category_is_hidden(): void
    {
        $category = $this->publishedCategory(['published_at' => null]);
        $this->series($this->publishedCourse($category));

        $this->assertSame([], EnrollingNow::compute());
    }

    public function test_categories_are_ordered_by_display_order(): void
    {
        $second = $this->publishedCategory(['name' => 'Béčko', 'display_order' => 2]);
        $first = $this->publishedCategory(['name' => 'Áčko', 'display_order' => 1]);
        $this->series($this->publishedCourse($second));
        $this->series($this->publishedCourse($first));

        $titles = array_column(EnrollingNow::compute(), 'title');

        $this->assertSame(['Áčko', 'Béčko'], $titles);
    }

    public function test_brick_renders_nothing_on_the_public_site_when_nothing_is_enrolling(): void
    {
        // No open runs -> the section is hidden entirely instead of rendering
        // an empty shell with just the heading.
        $this->assertSame('', EnrollingNowBrick::toHtml(['title' => 'Právě přihlašujeme']));
    }

    public function test_rendered_brick_links_the_category_and_each_course(): void
    {
        $category = $this->publishedCategory(['name' => 'Jóga', 'slug' => 'joga']);
        $course = $this->publishedCourse($category, ['name' => 'Hormonální jóga', 'slug' => 'hormonalni-joga']);
        $this->series($course);

        $html = EnrollingNowBrick::toHtml(['title' => 'Právě přihlašujeme']);

        $this->assertStringContainsString('kategorie=joga', $html);
        $this->assertStringContainsString('/kurzy/hormonalni-joga', $html);
        $this->assertStringContainsString('Hormonální jóga', $html);
    }
}
