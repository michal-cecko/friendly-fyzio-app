<?php

namespace Tests\Feature;

use App\Enums\ServiceVisibility;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Support\InternalLinks;
use App\Support\LinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $event = Lesson::factory()->forCategory($eventCategory)->create(['published_at' => now(), 'lesson_date' => today()->addWeek()]);

        $this->assertSame(url('/kurzy').'?kategorie=hormonalni-joga', InternalLinks::resolve("course-category:{$category->id}"));
        $this->assertSame($course->permalink(), InternalLinks::resolve("course:{$course->id}"));
        $this->assertSame($eventCategory->permalink, InternalLinks::resolve("event-category:{$eventCategory->id}"));
        $this->assertSame($event->permalink(), InternalLinks::resolve("event:{$event->id}"));
    }

    public function test_legacy_lesson_and_workshop_references_resolve_via_preserved_event_ids(): void
    {
        // Lessons and workshops merged into one-off events with PRESERVED ids,
        // so old stored refs must keep resolving to the event permalink.
        $event = Lesson::factory()->create(['published_at' => now(), 'lesson_date' => today()->addWeek()]);

        $this->assertSame($event->permalink(), InternalLinks::resolve("lesson:{$event->id}"));
        $this->assertSame($event->permalink(), InternalLinks::resolve("workshop:{$event->id}"));
    }

    public function test_options_include_course_categories_courses_events_and_event_categories(): void
    {
        $category = CourseCategory::factory()->create(['published_at' => now()]);
        $course = Course::factory()->create(['published_at' => now()]);
        $eventCategory = EventCategory::factory()->create();
        $event = Lesson::factory()->forCategory($eventCategory)->create(['published_at' => now(), 'lesson_date' => today()->addWeek()]);

        $options = InternalLinks::options();

        $this->assertArrayHasKey("course-category:{$category->id}", $options['Kurzy – kategorie']);
        $this->assertArrayHasKey("course:{$course->id}", $options['Kurzy – detail']);
        $this->assertArrayHasKey("event-category:{$eventCategory->id}", $options['Lekce – kategorie']);
        $this->assertArrayHasKey("event:{$event->id}", $options['Lekce – detail']);
    }

    public function test_options_are_grouped_and_include_pages_routes_and_categories(): void
    {
        $page = Page::factory()->create();
        $category = ServiceCategory::factory()->create();

        $options = InternalLinks::options();

        $this->assertArrayHasKey("page:{$page->id}", $options['Stránky']);
        $this->assertArrayHasKey('route:reservation.wizard', $options['Rezervace a přihlášení']);
        $this->assertArrayHasKey("category:{$category->id}", $options['Služby – kategorie']);
    }

    public function test_options_exclude_unpublished_and_owner_attached_pages_and_categories(): void
    {
        $draftPage = Page::factory()->draft()->create();
        $draftCategory = ServiceCategory::factory()->unpublished()->create();
        // A page attached to an owner canonicalises to that owner's URL, so it is
        // offered as the owner, never as a standalone page.
        $service = Service::factory()->create();
        $attachedPage = Page::factory()->for($service, 'pageable')->create();

        $options = InternalLinks::options();

        $this->assertArrayNotHasKey("page:{$draftPage->id}", $options['Stránky'] ?? []);
        $this->assertArrayNotHasKey("page:{$attachedPage->id}", $options['Stránky'] ?? []);
        $this->assertArrayNotHasKey("category:{$draftCategory->id}", $options['Služby – kategorie'] ?? []);
    }

    public function test_options_include_public_services_and_hidden_ones_with_a_custom_page(): void
    {
        $public = Service::factory()->create([
            'name' => 'Vstupní vyšetření',
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        // Topic/landing services are hidden from booking but still render their page.
        $hiddenWithPage = Service::factory()->create([
            'name' => 'Terapie pánevního dna',
            'visibility' => ServiceVisibility::Hidden,
            'published_at' => now(),
        ]);
        Page::factory()->for($hiddenWithPage, 'pageable')->create();

        $hidden = Service::factory()->create([
            'name' => 'Interní konzultace',
            'visibility' => ServiceVisibility::Hidden,
            'published_at' => now(),
        ]);

        $options = InternalLinks::options();

        $this->assertArrayHasKey("service:{$public->id}", $options['Služby – detail']);
        $this->assertArrayHasKey("service:{$hiddenWithPage->id}", $options['Služby – detail']);
        $this->assertArrayNotHasKey("service:{$hidden->id}", $options['Služby – detail']);

        $this->assertSame(
            "Vstupní vyšetření ({$public->category->name})",
            $options['Služby – detail']["service:{$public->id}"],
        );
    }

    public function test_therapists_are_pickable_and_resolve_to_their_public_profile(): void
    {
        $therapist = StaffProfile::factory()->published()->create();
        $unpublished = StaffProfile::factory()->unpublished()->create();

        $options = InternalLinks::options();

        $this->assertArrayHasKey("therapist:{$therapist->id}", $options['Tým a terapeuti']);
        $this->assertArrayNotHasKey("therapist:{$unpublished->id}", $options['Tým a terapeuti']);
        $this->assertSame($therapist->user->full_name, $options['Tým a terapeuti']["therapist:{$therapist->id}"]);

        $this->assertSame($therapist->permalink, InternalLinks::resolve("therapist:{$therapist->id}"));
        $this->assertSame(route('therapist.show', $therapist->slug), InternalLinks::resolve("therapist:{$therapist->id}"));
    }

    public function test_options_for_returns_a_single_kind(): void
    {
        $page = Page::factory()->create();
        $therapist = StaffProfile::factory()->published()->create();

        $this->assertArrayHasKey("page:{$page->id}", InternalLinks::optionsFor('page'));
        $this->assertArrayNotHasKey("therapist:{$therapist->id}", InternalLinks::optionsFor('page'));
        $this->assertArrayHasKey('route:public.login', InternalLinks::optionsFor('route'));
        $this->assertSame([], InternalLinks::optionsFor('does-not-exist'));
        $this->assertSame([], InternalLinks::optionsFor(null));
    }

    public function test_kind_of_reads_the_kind_and_folds_legacy_kinds(): void
    {
        $this->assertSame('page', InternalLinks::kindOf('page:019f94b0-416e-7338-a0dd-3c1876ebb19e'));
        $this->assertSame('therapist', InternalLinks::kindOf('therapist:019f94b0-416e-7338-a0dd-3c1876ebb19e'));
        $this->assertSame('event', InternalLinks::kindOf('lesson:019f94b0-416e-7338-a0dd-3c1876ebb19e'));
        $this->assertSame('event', InternalLinks::kindOf('workshop:019f94b0-416e-7338-a0dd-3c1876ebb19e'));
        $this->assertNull(InternalLinks::kindOf('nonsense:1'));
        $this->assertNull(InternalLinks::kindOf(null));
    }

    public function test_label_names_a_reference_that_is_no_longer_offered(): void
    {
        // Drafts drop out of the picker options but a stored link must still
        // display its target rather than an empty select.
        $draft = Page::factory()->draft()->create(['title' => 'Chystaná stránka']);

        $this->assertSame('Chystaná stránka', InternalLinks::label("page:{$draft->id}"));
        $this->assertSame('Rezervace', InternalLinks::label('route:reservation.wizard'));
        $this->assertNull(InternalLinks::label('page:'.Str::uuid()));
        $this->assertNull(InternalLinks::label(null));
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
