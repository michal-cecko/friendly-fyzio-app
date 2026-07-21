<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\EventCategory;
use App\Models\NavigationItem;
use App\Models\OneOffEvent;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\InternalLinks;
use App\Support\LinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_links_resolves_each_reference_kind(): void
    {
        $page = Page::factory()->create();
        $category = ServiceCategory::factory()->create();

        $service = Service::factory()->create();

        $this->assertSame($page->permalink, InternalLinks::resolve("page:{$page->id}"));
        $this->assertSame($category->permalink, InternalLinks::resolve("category:{$category->id}"));
        $this->assertSame($service->permalink, InternalLinks::resolve("service:{$service->id}"));
        $this->assertSame(route('reservation.wizard'), InternalLinks::resolve('route:reservation.wizard'));
        $this->assertNull(InternalLinks::resolve('route:does.not.exist'));
        $this->assertNull(InternalLinks::resolve(null));
        $this->assertNull(InternalLinks::resolve(''));
    }

    public function test_internal_links_resolves_course_and_event_destinations(): void
    {
        $category = CourseCategory::factory()->create(['published_at' => now(), 'slug' => 'hormonalni-joga']);
        $course = Course::factory()->create(['published_at' => now()]);
        // The data migration pre-seeds the canonical categories in every database.
        $eventCategory = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        $event = OneOffEvent::factory()->forCategory($eventCategory)->create(['published_at' => now(), 'event_date' => today()->addWeek()]);

        $this->assertSame(url('/kurzy').'?kategorie=hormonalni-joga', InternalLinks::resolve("course-category:{$category->id}"));
        $this->assertSame($course->permalink(), InternalLinks::resolve("course:{$course->id}"));
        $this->assertSame($eventCategory->permalink, InternalLinks::resolve("event-category:{$eventCategory->id}"));
        $this->assertSame($event->permalink(), InternalLinks::resolve("event:{$event->id}"));
    }

    public function test_legacy_lesson_and_workshop_references_resolve_via_preserved_event_ids(): void
    {
        // Lessons and workshops merged into one-off events with PRESERVED ids,
        // so old stored refs must keep resolving to the event permalink.
        $event = OneOffEvent::factory()->create(['published_at' => now(), 'event_date' => today()->addWeek()]);

        $this->assertSame($event->permalink(), InternalLinks::resolve("lesson:{$event->id}"));
        $this->assertSame($event->permalink(), InternalLinks::resolve("workshop:{$event->id}"));
    }

    public function test_options_include_course_categories_courses_events_and_event_categories(): void
    {
        $category = CourseCategory::factory()->create(['published_at' => now()]);
        $course = Course::factory()->create(['published_at' => now()]);
        $eventCategory = EventCategory::factory()->create();
        $event = OneOffEvent::factory()->forCategory($eventCategory)->create(['published_at' => now(), 'event_date' => today()->addWeek()]);

        $options = InternalLinks::options();

        $this->assertArrayHasKey("course-category:{$category->id}", $options['Kurzy – kategorie']);
        $this->assertArrayHasKey("course:{$course->id}", $options['Kurzy – detail']);
        $this->assertArrayHasKey("event-category:{$eventCategory->id}", $options['Jednorázové akce – kategorie']);
        $this->assertArrayHasKey("event:{$event->id}", $options['Jednorázové akce']);
    }

    public function test_options_are_grouped_and_include_pages_routes_and_categories(): void
    {
        $page = Page::factory()->create();
        $category = ServiceCategory::factory()->create();

        $options = InternalLinks::options();

        $this->assertArrayHasKey("page:{$page->id}", $options['Stránky']);
        $this->assertArrayHasKey('route:reservation.wizard', $options['Rezervace a přihlášení']);
        $this->assertArrayHasKey("category:{$category->id}", $options['Služby']);
    }

    public function test_options_include_only_services_with_a_custom_page(): void
    {
        $withPage = Service::factory()->create(['name' => 'Terapie pánevního dna']);
        Page::factory()->for($withPage, 'pageable')->create();
        $withoutPage = Service::factory()->create(['name' => 'Vstupní vyšetření']);

        $options = InternalLinks::options();

        $this->assertArrayHasKey("service:{$withPage->id}", $options['Stránky služeb']);
        $this->assertArrayNotHasKey("service:{$withoutPage->id}", $options['Stránky služeb'] ?? []);
    }

    public function test_resolver_reads_the_new_reference_shape(): void
    {
        $this->assertSame(
            route('reservation.wizard'),
            LinkResolver::resolve(['link_type' => 'internal', 'link_ref' => 'route:reservation.wizard']),
        );
    }

    public function test_resolver_still_reads_the_legacy_page_shape(): void
    {
        $page = Page::factory()->create();

        $this->assertSame(
            $page->permalink,
            LinkResolver::resolve(['link_type' => 'page', 'page_id' => $page->id]),
        );
    }

    public function test_resolver_returns_custom_url_and_from_config_reads_prefix(): void
    {
        $this->assertSame(
            'https://example.com',
            LinkResolver::resolve(['link_type' => 'custom', 'url' => 'https://example.com']),
        );

        $this->assertSame(
            route('reservation.wizard'),
            LinkResolver::fromConfig(['cta_link_type' => 'internal', 'cta_link_ref' => 'route:reservation.wizard'], 'cta_'),
        );
    }

    public function test_navigation_item_resolves_via_link_ref(): void
    {
        $item = NavigationItem::factory()->create([
            'label' => 'Rezervace',
            'link_type' => 'internal',
            'link_ref' => 'route:reservation.wizard',
        ]);

        $this->assertSame(route('reservation.wizard'), $item->resolvedUrl());
    }
}
