<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\DayOfWeek;
use App\Enums\ReservationStatus;
use App\Enums\WeekType;
use App\Filament\Pages\Problems;
use App\Filament\Pages\Suggestions;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\ProblemsWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\SuggestionsWidget;
use App\Filament\Widgets\UpcomingLessonsWidget;
use App\Filament\Widgets\UpcomingReservationsWidget;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use App\Models\WaitlistEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Suggestions\TherapistScopedSuggestionsTest;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /** @return list<array{class-string}> */
    public static function widgetProvider(): array
    {
        return [
            [AdminStatsOverview::class],
            [UpcomingReservationsWidget::class],
            [UpcomingLessonsWidget::class],
        ];
    }

    /**
     * The two work-list widgets read per therapist, so they are staff-wide;
     * everything else on this dashboard is a clinic-wide figure.
     *
     * @return list<array{class-string}>
     */
    public static function staffWidgetProvider(): array
    {
        return [
            [ProblemsWidget::class],
            [SuggestionsWidget::class],
        ];
    }

    /**
     * @param  class-string  $widget
     */
    #[DataProvider('widgetProvider')]
    public function test_widgets_are_visible_only_to_admins(string $widget): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue($widget::canView());

        $this->actingAs(User::factory()->therapist()->create());
        $this->assertFalse($widget::canView());

        $this->actingAs(User::factory()->customer()->create());
        $this->assertFalse($widget::canView());
    }

    /**
     * @param  class-string  $widget
     */
    #[DataProvider('staffWidgetProvider')]
    public function test_work_list_widgets_are_visible_to_every_staff_member(string $widget): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue($widget::canView());

        $this->actingAs(User::factory()->therapist()->create());
        $this->assertTrue($widget::canView());

        // Lecturers have their own clashes and their own backlog.
        $this->actingAs(User::factory()->lecturer()->create());
        $this->assertTrue($widget::canView());

        $this->actingAs(User::factory()->customer()->create());
        $this->assertFalse($widget::canView());
    }

    public function test_revenue_chart_requires_the_revenue_capability_not_merely_admin(): void
    {
        // Being an admin — even a super-admin — is not enough on its own.
        $this->actingAs(User::factory()->admin()->create());
        $this->assertFalse(RevenueChartWidget::canView());

        $this->actingAs(User::factory()->admin()->revenue()->create());
        $this->assertTrue(RevenueChartWidget::canView());
    }

    public function test_stats_show_pending_and_utilization(): void
    {
        Reservation::factory()->count(2)->create(['reservation_date' => today(), 'status' => ReservationStatus::Confirmed]);
        Reservation::factory()->create(['reservation_date' => today(), 'status' => ReservationStatus::Pending]);
        Reservation::factory()->create(['reservation_date' => today(), 'status' => ReservationStatus::Cancelled]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(AdminStatsOverview::class)
            ->assertSee('Dnešní rezervace')
            ->assertSee('Čeká na potvrzení')
            ->assertSee('Noví klienti')
            ->assertSee('Obsazenost tento týden')
            ->assertDontSee('Nezaplacené platby')
            ->assertDontSee('Aktivní kurzy');
    }

    public function test_upcoming_reservations_lists_future_and_hides_cancelled_and_past(): void
    {
        $upcoming = Reservation::factory()->create([
            'reservation_date' => today()->addDay(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $cancelled = Reservation::factory()->create([
            'reservation_date' => today()->addDay(),
            'status' => ReservationStatus::Cancelled,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);
        $past = Reservation::factory()->create([
            'reservation_date' => today()->subDay(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UpcomingReservationsWidget::class)
            ->assertCanSeeTableRecords([$upcoming])
            ->assertCanNotSeeTableRecords([$cancelled, $past]);
    }

    public function test_upcoming_lessons_lists_course_and_standalone_lessons_and_hides_past_ones(): void
    {
        $courseLesson = Lesson::factory()->create([
            'lesson_date' => today(),
            'start_time' => '23:00',
            'end_time' => '23:59',
        ]);
        $standalone = Lesson::factory()->standalone()->create([
            'lesson_date' => today()->addDay(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $past = Lesson::factory()->create([
            'lesson_date' => today()->subDay(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UpcomingLessonsWidget::class)
            ->assertCanSeeTableRecords([$courseLesson, $standalone])
            ->assertCanNotSeeTableRecords([$past]);
    }

    public function test_upcoming_lessons_hides_a_lesson_that_has_already_finished_today(): void
    {
        $this->travelTo(today()->setTime(14, 0));

        $finished = Lesson::factory()->create([
            'lesson_date' => today(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $stillRunning = Lesson::factory()->create([
            'lesson_date' => today(),
            'start_time' => '13:30',
            'end_time' => '14:30',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UpcomingLessonsWidget::class)
            ->assertCanSeeTableRecords([$stillRunning])
            ->assertCanNotSeeTableRecords([$finished]);
    }

    public function test_problems_widget_shows_a_conflict_with_resolve_link_or_empty_state(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // No conflicts → positive empty state.
        Livewire::test(ProblemsWidget::class)->assertSee('Vše v pořádku, žádné problémy.');

        // Manufacture a same-room double-booking tomorrow.
        $room = Room::factory()->create();
        $a = Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:00', 'end_time' => '10:00']);
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:30', 'end_time' => '10:30']);

        Livewire::test(ProblemsWidget::class)
            ->assertSee('Vyřešit')
            ->assertSee('Dvojí rezervace místnosti');
    }

    public function test_dashboard_renders_for_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')->assertOk();
    }

    public function test_dashboard_has_no_quick_actions(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // The quick-action buttons were removed from the dashboard header.
        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('Nová rezervace')
            ->assertDontSee('Přidat klienta')
            ->assertDontSee('Další akce');
    }

    public function test_suggestions_widget_shows_the_empty_state_until_something_needs_deciding(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SuggestionsWidget::class)->assertSee('Nic nečeká na rozhodnutí.');

        Reservation::factory()->create([
            'reservation_date' => today()->addDay(),
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);
        Once::flush();

        Livewire::test(SuggestionsWidget::class)
            ->assertSee('Nevyřízené doporučení lékaře')
            ->assertSee('Přejít');
    }

    public function test_suggestions_widget_caps_the_card_and_links_to_the_full_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Six cards from one rule: five fit on the dashboard, the rest live on
        // the Návrhy page.
        for ($i = 0; $i < 6; $i++) {
            $series = CourseSeries::factory()->create([
                'capacity' => 5,
                'start_date' => today()->subWeek(),
                'end_date' => today()->addMonth(),
                'status' => CourseSeriesStatus::Open,
            ]);
            CourseEnrollment::factory()->create(['series_id' => $series->id, 'status' => CourseEnrollmentStatus::Active]);
            WaitlistEntry::factory()->forWaitlistable($series)->create(['notified_at' => null]);
        }

        Livewire::test(SuggestionsWidget::class)
            ->assertSee('Zobrazit všech 6 návrhů')
            ->assertSeeHtml(Suggestions::getUrl());
    }

    public function test_suggestions_page_lists_cards_by_group(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Reservation::factory()->create([
            'reservation_date' => today()->addDay(),
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);

        $this->get(Suggestions::getUrl())
            ->assertOk()
            ->assertSee('Rezervace')
            ->assertSee('Nevyřízené doporučení lékaře');
    }

    public function test_suggestions_page_badge_counts_open_cards_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertNull(Suggestions::getNavigationBadge());

        Reservation::factory()->create([
            'reservation_date' => today()->addDay(),
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);
        Once::flush();
        Cache::flush();

        $this->assertSame('1', Suggestions::getNavigationBadge());
    }

    /**
     * Therapists reach both work lists, narrowed to their own records; see
     * {@see TherapistScopedSuggestionsTest}.
     */
    public function test_problems_and_suggestions_pages_are_staff_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(Problems::canAccess());
        $this->assertTrue(Suggestions::canAccess());

        $this->actingAs(User::factory()->therapist()->create());
        $this->assertTrue(Problems::canAccess());
        $this->assertTrue(Suggestions::canAccess());

        $this->actingAs(User::factory()->lecturer()->create());
        $this->assertTrue(Problems::canAccess());
        $this->assertTrue(Suggestions::canAccess());

        $this->actingAs(User::factory()->customer()->create());
        $this->assertFalse(Problems::canAccess());
        $this->assertFalse(Suggestions::canAccess());
    }

    public function test_problems_page_badge_reflects_conflict_count(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->assertNull(Problems::getNavigationBadge());

        $room = Room::factory()->create();
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:00', 'end_time' => '10:00']);
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:30', 'end_time' => '10:30']);
        Once::flush();

        // Same room, two (different, factory-random) therapists → one room conflict.
        $this->assertSame('1', Problems::getNavigationBadge());
        $this->assertSame('danger', Problems::getNavigationBadgeColor());
    }

    /**
     * Both work lists are reached from the topbar, next to the search — they are
     * something you glance at from wherever you are, not a section you navigate
     * into, so neither takes a sidebar slot.
     */
    public function test_problems_and_suggestions_live_in_the_topbar_not_the_sidebar(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertFalse(Problems::shouldRegisterNavigation());
        $this->assertFalse(Suggestions::shouldRegisterNavigation());

        // Icon-only: the label survives as the accessible name and the tooltip,
        // never as visible text next to the glyph.
        $this->get('/admin')
            ->assertOk()
            ->assertSee('aria-label="Problémy"', false)
            ->assertSee('aria-label="Návrhy"', false)
            ->assertSee(Problems::getUrl())
            ->assertSee(Suggestions::getUrl());
    }

    public function test_the_topbar_problem_icon_carries_the_conflict_badge(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $room = Room::factory()->create();
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:00', 'end_time' => '10:00']);
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:30', 'end_time' => '10:30']);

        $this->get('/admin')
            ->assertOk()
            ->assertSee(Problems::getUrl());
    }

    public function test_customers_never_see_the_topbar_work_list_icons(): void
    {
        $this->actingAs(User::factory()->customer()->create());

        $this->assertFalse(Problems::canAccess());
        $this->assertFalse(Suggestions::canAccess());
    }

    public function test_problems_page_lists_conflicts_for_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $room = Room::factory()->create();
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:00', 'end_time' => '10:00']);
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:30', 'end_time' => '10:30']);

        $this->get(Problems::getUrl())
            ->assertOk()
            ->assertSee('Dvojí rezervace místnosti');
    }

    /**
     * A rental blocking sitting inside a therapist's working hours — normal, and
     * already subtracted by the slot engine, so it gets its own section and a
     * warning badge rather than a red count.
     */
    private function manufactureExpectedOverlap(): void
    {
        $room = Room::factory()->create();
        $tomorrow = today()->addDay();

        TherapistWorkBlock::factory()->create([
            'room_id' => $room->id,
            'work_date' => $tomorrow->toDateString(),
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);

        RoomBlocking::factory()->recurring()->create([
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::fromCarbon($tomorrow),
            'week_type' => WeekType::All,
            'start_time' => '16:00',
            'end_time' => '18:00',
        ]);
    }

    public function test_problems_page_separates_expected_overlaps_from_real_conflicts(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->manufactureExpectedOverlap();

        $this->get(Problems::getUrl())
            ->assertOk()
            // No real conflict, so the reassuring empty state still shows…
            ->assertSee('Vše v pořádku, žádné problémy.')
            // …alongside the expected-overlap section.
            ->assertSee('Očekávané překryvy')
            ->assertSee('Blokace uvnitř pracovní doby');
    }

    public function test_expected_overlaps_get_a_warning_badge_not_a_danger_one(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->manufactureExpectedOverlap();
        Once::flush();

        $this->assertSame('warning', Problems::getNavigationBadgeColor());
        $this->assertSame('1', Problems::getNavigationBadge());
    }

    public function test_a_lesson_clashing_with_working_hours_reaches_the_problems_widget(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $room = Room::factory()->create();
        $tomorrow = today()->addDay();

        TherapistWorkBlock::factory()->create([
            'room_id' => $room->id,
            'work_date' => $tomorrow->toDateString(),
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        Lesson::factory()->create([
            'room_id' => $room->id,
            'lesson_date' => $tomorrow->toDateString(),
            'start_time' => '17:00',
            'end_time' => '18:00',
        ]);

        Livewire::test(ProblemsWidget::class)
            ->assertSee('Lekce zabírá místnost v pracovní době')
            ->assertSee('Vyřešit');
    }
}
