<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Livewire\CourseArchive;
use App\Livewire\LessonArchive;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\LessonBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LessonArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_pills_switch_between_categories_when_not_fixed(): void
    {
        $workshopy = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        Lesson::factory()->standalone()->forCategory($workshopy)->published()->create([
            'name' => 'Workshop zdravých zad',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(LessonArchive::class)
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

        Lesson::factory()->standalone()->forCategory($workshopy)->published()->create([
            'name' => 'Workshop zdravých zad',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(LessonArchive::class, ['config' => ['category' => 'workshopy']])
            ->assertSee('Workshop zdravých zad')
            ->assertDontSee('Ochutnávková lekce jógy')
            // The category pills disappear when the archive is pinned.
            ->assertDontSee('Jednorázové lekce');
    }

    public function test_search_filters_and_past_tail_shows_only_on_the_unfiltered_first_page(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();

        Lesson::factory()->standalone()->forCategory($category)->published()->create([
            'name' => 'Workshop zdravých zad',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        Lesson::factory()->standalone()->forCategory($category)->published()->create([
            'name' => 'Proběhlý seminář',
            'lesson_date' => today()->subWeeks(2)->toDateString(),
        ]);

        Livewire::test(LessonArchive::class)
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

        Lesson::factory()->standalone()->forCategory($category)->published()->create([
            'name' => 'Volný workshop',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 8,
        ]);

        $full = Lesson::factory()->standalone()->forCategory($category)->published()->create([
            'name' => 'Plný workshop',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 1,
        ]);

        // A pending booking holds the spot, so it must count as taken.
        LessonBooking::factory()->create([
            'lesson_id' => $full->getKey(),
            'status' => BookingStatus::Pending,
        ]);

        Livewire::test(LessonArchive::class)
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
            Lesson::factory()->standalone()->forCategory($category)->published()->create([
                'name' => "Workshop číslo {$week}",
                'lesson_date' => today()->addWeeks($week)->toDateString(),
            ]);
        }

        Livewire::test(LessonArchive::class)
            ->assertSee('Workshop číslo 1')
            ->assertDontSee('Workshop číslo 7')
            ->call('gotoPage', 2, 'strana')
            ->assertSee('Workshop číslo 7')
            ->assertDontSee('Workshop číslo 1');
    }

    public function test_course_archive_has_no_type_switch_without_the_brick_toggle(): void
    {
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'lesson_date' => today()->addWeeks(1)->toDateString(),
        ]);

        // Even a hand-crafted ?typ=lekce must not switch an archive whose brick
        // never opted into the tabs.
        Livewire::test(CourseArchive::class)
            ->assertDontSee('Pravidelné semestrální série lekcí')
            ->set('type', 'lekce')
            ->assertDontSee('Ochutnávková lekce jógy');
    }

    public function test_course_archive_type_switch_lists_events_on_the_lekce_tab(): void
    {
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        $course = Course::factory()->create(['published_at' => now(), 'name' => 'Jin jóga']);
        CourseSeries::factory()->for($course)->create([
            'status' => CourseSeriesStatus::Open,
            'visibility' => CourseSeriesVisibility::Public,
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
        ]);

        Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'lesson_date' => today()->addWeeks(1)->toDateString(),
        ]);

        Livewire::test(CourseArchive::class, ['config' => $this->switchConfig()])
            ->assertSee('Pohybové kurzy')
            ->assertSee('Jednotlivé lekce bez závazku')
            ->assertSee('Jin jóga')
            ->assertDontSee('Ochutnávková lekce jógy')
            ->call('selectType', 'lekce')
            ->assertSee('Ochutnávková lekce jógy')
            ->assertDontSee('Jin jóga');
    }

    public function test_course_archive_lekce_tab_is_scoped_to_the_configured_categories(): void
    {
        $workshopy = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        Lesson::factory()->standalone()->forCategory($workshopy)->published()->create([
            'name' => 'Workshop zdravých zad',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        // One configured category pins the tab to it — and needs no pills.
        Livewire::test(CourseArchive::class, ['config' => $this->switchConfig()])
            ->call('selectType', 'lekce')
            ->assertSee('Ochutnávková lekce jógy')
            ->assertDontSee('Workshop zdravých zad')
            ->assertDontSee('Vše');
    }

    public function test_course_archive_lekce_tab_shows_pills_for_several_configured_categories(): void
    {
        $workshopy = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        Lesson::factory()->standalone()->forCategory($workshopy)->published()->create([
            'name' => 'Workshop zdravých zad',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Ochutnávková lekce jógy',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(CourseArchive::class, [
            'config' => $this->switchConfig(['workshopy', 'jednorazove-lekce']),
        ])
            ->call('selectType', 'lekce')
            ->assertSee('Vše')
            ->assertSee('Workshop zdravých zad')
            ->assertSee('Ochutnávková lekce jógy')
            ->call('selectCategory', 'workshopy')
            ->assertSee('Workshop zdravých zad')
            ->assertDontSee('Ochutnávková lekce jógy');
    }

    public function test_switching_tabs_clears_the_category_pill(): void
    {
        // The two tabs use different taxonomies, so a leftover course-category
        // slug would otherwise silently empty the events tab.
        Livewire::test(CourseArchive::class, ['config' => $this->switchConfig([])])
            ->set('category', 'joga')
            ->call('selectType', 'lekce')
            ->assertSet('category', null);
    }

    public function test_course_archive_lekce_tab_honours_the_availability_filter(): void
    {
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        $full = Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Obsazená lekce',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 1,
        ]);
        LessonBooking::factory()->for($full)->create(['status' => BookingStatus::Confirmed]);

        Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
            'name' => 'Volná lekce',
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 10,
        ]);

        Livewire::test(CourseArchive::class, ['config' => $this->switchConfig()])
            ->call('selectType', 'lekce')
            ->assertSee('Obsazená lekce')
            ->assertSee('Volná lekce')
            ->set('availableOnly', true)
            ->assertSee('Volná lekce')
            ->assertDontSee('Obsazená lekce');
    }

    public function test_course_archive_lekce_tab_paginates_six_events_per_page(): void
    {
        $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->firstOrFail();

        foreach (range(1, 7) as $index) {
            Lesson::factory()->standalone()->forCategory($lekce)->published()->create([
                'name' => 'Lekce číslo '.$index,
                'lesson_date' => today()->addWeeks($index)->toDateString(),
            ]);
        }

        Livewire::test(CourseArchive::class, ['config' => $this->switchConfig()])
            ->call('selectType', 'lekce')
            ->assertSee('Lekce číslo 1')
            ->assertDontSee('Lekce číslo 7')
            ->call('gotoPage', 2, 'strana')
            ->assertSee('Lekce číslo 7')
            ->assertDontSee('Lekce číslo 1');
    }

    /**
     * The course-archive brick config that turns the tabs on.
     *
     * @param  array<int, string>  $categories
     * @return array<string, mixed>
     */
    private function switchConfig(array $categories = ['jednorazove-lekce']): array
    {
        return [
            'show_type_switch' => true,
            'event_categories' => $categories,
        ];
    }
}
