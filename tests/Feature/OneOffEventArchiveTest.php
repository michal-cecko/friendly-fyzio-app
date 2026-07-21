<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Livewire\CourseArchive;
use App\Livewire\OneOffEventArchive;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneOffEventArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_pills_switch_between_categories_when_not_fixed(): void
    {
        $workshopy = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        OneOffEvent::factory()->forCategory($workshopy)->published()->create([
            'name' => 'Workshop zdravých zad',
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);
        OneOffEvent::factory()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(OneOffEventArchive::class)
            ->assertSee('Workshopy')
            ->assertSee('Jednorázové lekce')
            ->assertSee('Workshop zdravých zad')
            ->assertSee('Ochutnávková lekce jógy')
            ->call('selectCategory', 'workshopy')
            ->assertSee('Workshop zdravých zad')
            ->assertDontSee('Ochutnávková lekce jógy');
    }

    public function test_brick_category_config_pins_the_archive_and_hides_the_pills(): void
    {
        $workshopy = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        OneOffEvent::factory()->forCategory($workshopy)->published()->create([
            'name' => 'Workshop zdravých zad',
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);
        OneOffEvent::factory()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(OneOffEventArchive::class, ['config' => ['category' => 'workshopy']])
            ->assertSee('Workshop zdravých zad')
            ->assertDontSee('Ochutnávková lekce jógy')
            // The category pills disappear when the archive is pinned.
            ->assertDontSee('Jednorázové lekce');
    }

    public function test_search_filters_and_past_tail_shows_only_on_the_unfiltered_first_page(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();

        OneOffEvent::factory()->forCategory($category)->published()->create([
            'name' => 'Workshop zdravých zad',
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);
        OneOffEvent::factory()->forCategory($category)->published()->create([
            'name' => 'Proběhlý seminář',
            'event_date' => today()->subWeeks(2)->toDateString(),
        ]);

        Livewire::test(OneOffEventArchive::class)
            ->assertSee('Workshop zdravých zad')
            ->assertSee('Proběhlé akce')
            ->assertSee('Proběhlý seminář')
            ->set('search', 'zdravých')
            ->assertSee('Workshop zdravých zad')
            ->assertDontSee('Proběhlé akce')
            ->assertDontSee('Proběhlý seminář');
    }

    public function test_available_only_hides_full_events(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();

        OneOffEvent::factory()->forCategory($category)->published()->create([
            'name' => 'Volný workshop',
            'event_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 8,
        ]);

        $full = OneOffEvent::factory()->forCategory($category)->published()->create([
            'name' => 'Plný workshop',
            'event_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 1,
        ]);

        // A pending booking holds the spot, so it must count as taken.
        OneOffEventBooking::factory()->create([
            'one_off_event_id' => $full->getKey(),
            'status' => BookingStatus::Pending,
        ]);

        Livewire::test(OneOffEventArchive::class)
            ->assertSee('Volný workshop')
            ->assertSee('Plný workshop')
            ->set('availableOnly', true)
            ->assertSee('Volný workshop')
            ->assertDontSee('Plný workshop');
    }

    public function test_archive_paginates_six_per_page(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();

        foreach (range(1, 7) as $week) {
            OneOffEvent::factory()->forCategory($category)->published()->create([
                'name' => "Workshop číslo {$week}",
                'event_date' => today()->addWeeks($week)->toDateString(),
            ]);
        }

        Livewire::test(OneOffEventArchive::class)
            ->assertSee('Workshop číslo 1')
            ->assertDontSee('Workshop číslo 7')
            ->call('gotoPage', 2, 'strana')
            ->assertSee('Workshop číslo 7')
            ->assertDontSee('Workshop číslo 1');
    }

    public function test_course_archive_no_longer_has_the_lesson_type_toggle(): void
    {
        $this->assertFalse(property_exists(CourseArchive::class, 'type'));
    }

    public function test_course_archive_renders_the_cross_sell_section_with_course_linked_events(): void
    {
        EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        $course = Course::factory()->create(['published_at' => now(), 'name' => 'Jin jóga']);
        CourseSeries::factory()->for($course)->create([
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
        ]);

        $event = OneOffEvent::factory()->withCourse($course)->published()->create([
            'name' => 'Ochutnávka Jin jógy',
            'event_date' => today()->addWeeks(1)->toDateString(),
        ]);

        Livewire::test(CourseArchive::class)
            ->assertSee('Chcete si to nejdřív vyzkoušet?')
            ->assertSee('Ochutnávka Jin jógy');
    }

    public function test_course_archive_cross_sell_can_be_disabled_by_config(): void
    {
        EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        $course = Course::factory()->create(['published_at' => now()]);
        OneOffEvent::factory()->withCourse($course)->published()->create([
            'name' => 'Ochutnávka Jin jógy',
            'event_date' => today()->addWeeks(1)->toDateString(),
        ]);

        Livewire::test(CourseArchive::class, ['config' => ['cross_sell' => false]])
            ->assertDontSee('Chcete si to nejdřív vyzkoušet?')
            ->assertDontSee('Ochutnávka Jin jógy');
    }
}
