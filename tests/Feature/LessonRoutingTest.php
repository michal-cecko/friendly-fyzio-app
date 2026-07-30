<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\EventCategory;
use App\Models\Lesson;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_detail_is_served_under_its_category_slug(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        $event = Lesson::factory()->standalone()->forCategory($category)->published()->create([
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        $this->get('/workshopy/'.$event->slug)
            ->assertOk()
            ->assertSee($event->name);
    }

    public function test_wrong_category_url_redirects_to_the_canonical_permalink(): void
    {
        $workshopy = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        $event = Lesson::factory()->standalone()->forCategory($workshopy)->published()->create([
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        $response = $this->get('/jednorazove-lekce/'.$event->slug);

        $response->assertStatus(301);
        $response->assertRedirect($event->permalink());
    }

    public function test_unknown_two_segment_url_falls_through_to_legacy_redirects(): void
    {
        $response = $this->get('/relaxace-ritualy/masaze');

        $response->assertStatus(301);
        $response->assertRedirect('/sluzby/relaxace/lymphaticke-masaze');
    }

    public function test_unknown_two_segment_url_without_a_legacy_target_is_404(): void
    {
        $this->get('/neexistujici-kategorie/neexistujici-akce')->assertNotFound();
    }

    public function test_the_lesson_tab_deep_link_opens_the_events_tab_instead_of_redirecting(): void
    {
        // ?typ=lekce is tab state on the course archive again, not a legacy URL.
        $this->seed(PageSeeder::class);

        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();
        Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        $this->get('/kurzy?typ=lekce')
            ->assertOk()
            ->assertSee('Ochutnávková lekce jógy');
    }

    public function test_old_lesson_url_redirects_to_the_event_permalink_by_preserved_id(): void
    {
        $category = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();
        $course = Course::factory()->create(['published_at' => now()]);
        $event = Lesson::factory()->standalone()->forCategory($category)->withCourse($course)->published()->create([
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        $response = $this->get('/kurzy/'.$course->slug.'/lekce/'.$event->getKey());

        $response->assertStatus(301);
        $response->assertRedirect($event->permalink());
    }

    public function test_old_lesson_url_with_unknown_id_falls_back_to_the_course_archive(): void
    {
        $response = $this->get('/kurzy/nejaky-kurz/lekce/neexistujici-id');

        $response->assertStatus(301);
        $response->assertRedirect('/kurzy');
    }

    public function test_legacy_one_time_entries_page_redirects_to_the_lessons_category(): void
    {
        $response = $this->get('/prihlaska-na-jednorazove-vstupy');

        $response->assertStatus(301);
        $response->assertRedirect('/jednorazove-lekce');
    }
}
