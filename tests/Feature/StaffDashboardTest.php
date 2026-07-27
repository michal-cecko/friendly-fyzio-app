<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\MyLessonsWidget;
use App\Filament\Widgets\MyScheduleWidget;
use App\Filament\Widgets\MyStatsOverview;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The staff half of the dashboard: widgets about the viewer's own work, which
 * appear per capability rather than per role. Contrast {@see AdminDashboardTest},
 * which covers the clinic-wide widgets.
 */
class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_my_widgets_follow_the_capability_not_the_absence_of_admin_rights(): void
    {
        // A pure therapist: their day, no lessons.
        $this->actingAs(User::factory()->therapist()->create());
        $this->assertTrue(MyStatsOverview::canView());
        $this->assertTrue(MyScheduleWidget::canView());
        $this->assertFalse(MyLessonsWidget::canView());

        // A pure lecturer: their lessons, no reservations.
        $this->actingAs(User::factory()->lecturer()->create());
        $this->assertTrue(MyStatsOverview::canView());
        $this->assertFalse(MyScheduleWidget::canView());
        $this->assertTrue(MyLessonsWidget::canView());

        // Both capabilities: both lists.
        $this->actingAs(User::factory()->therapist()->lecturer()->create());
        $this->assertTrue(MyScheduleWidget::canView());
        $this->assertTrue(MyLessonsWidget::canView());

        // An admin who also practises keeps their own day alongside the
        // clinic-wide figures — being an admin does not remove their patients.
        $this->actingAs(User::factory()->admin()->therapist()->create());
        $this->assertTrue(MyStatsOverview::canView());
        $this->assertTrue(MyScheduleWidget::canView());
        $this->assertTrue(AdminStatsOverview::canView());

        // An admin who neither treats nor teaches has no "my work" to show.
        $this->actingAs(User::factory()->admin()->create());
        $this->assertFalse(MyStatsOverview::canView());
        $this->assertFalse(MyScheduleWidget::canView());
        $this->assertFalse(MyLessonsWidget::canView());

        $this->actingAs(User::factory()->customer()->create());
        $this->assertFalse(MyStatsOverview::canView());
        $this->assertFalse(MyScheduleWidget::canView());
        $this->assertFalse(MyLessonsWidget::canView());
    }

    public function test_my_schedule_shows_only_my_own_upcoming_visits(): void
    {
        $therapist = User::factory()->therapist()->create();
        $colleague = User::factory()->therapist()->create();

        $mine = Reservation::factory()->create([
            'therapist_id' => $therapist->staffProfile->id,
            'reservation_date' => today()->addDay(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $theirs = Reservation::factory()->create([
            'therapist_id' => $colleague->staffProfile->id,
            'reservation_date' => today()->addDay(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);
        $cancelled = Reservation::factory()->create([
            'therapist_id' => $therapist->staffProfile->id,
            'reservation_date' => today()->addDay(),
            'status' => ReservationStatus::Cancelled,
            'start_time' => '13:00',
            'end_time' => '14:00',
        ]);
        $past = Reservation::factory()->create([
            'therapist_id' => $therapist->staffProfile->id,
            'reservation_date' => today()->subDay(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs($therapist);

        Livewire::test(MyScheduleWidget::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs, $cancelled, $past]);
    }

    public function test_my_lessons_shows_only_the_lessons_i_instruct(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $colleague = User::factory()->lecturer()->create();

        $mine = Lesson::factory()->create([
            'instructor_id' => $lecturer->id,
            'lesson_date' => today()->addDay(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $myStandaloneEvent = Lesson::factory()->standalone()->create([
            'instructor_id' => $lecturer->id,
            'lesson_date' => today()->addDays(2),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $theirs = Lesson::factory()->create([
            'instructor_id' => $colleague->id,
            'lesson_date' => today()->addDay(),
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);
        $past = Lesson::factory()->create([
            'instructor_id' => $lecturer->id,
            'lesson_date' => today()->subDay(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs($lecturer);

        Livewire::test(MyLessonsWidget::class)
            ->assertCanSeeTableRecords([$mine, $myStandaloneEvent])
            ->assertCanNotSeeTableRecords([$theirs, $past]);
    }

    public function test_stats_carry_only_the_blocks_the_viewer_has_capabilities_for(): void
    {
        $this->actingAs(User::factory()->therapist()->create());

        Livewire::test(MyStatsOverview::class)
            ->assertSee('Dnes mám')
            ->assertSee('Můj týden')
            ->assertSee('Tento měsíc')
            ->assertDontSee('Lekce tento týden');

        $this->actingAs(User::factory()->lecturer()->create());

        Livewire::test(MyStatsOverview::class)
            ->assertSee('Lekce tento týden')
            ->assertSee('Nejbližší lekce')
            ->assertDontSee('Dnes mám');

        $this->actingAs(User::factory()->therapist()->lecturer()->create());

        Livewire::test(MyStatsOverview::class)
            ->assertSee('Dnes mám')
            ->assertSee('Lekce tento týden');
    }

    public function test_todays_count_and_the_next_visit_are_mine_alone(): void
    {
        $this->travelTo(today()->setTime(8, 0));

        $therapist = User::factory()->therapist()->create();
        $colleague = User::factory()->therapist()->create();

        // Two of mine, an hour apart — one therapist cannot hold the same slot twice.
        Reservation::factory()->create([
            'therapist_id' => $therapist->staffProfile->id,
            'reservation_date' => today(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '14:30',
            'end_time' => '15:30',
        ]);
        Reservation::factory()->create([
            'therapist_id' => $therapist->staffProfile->id,
            'reservation_date' => today(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '15:30',
            'end_time' => '16:30',
        ]);
        Reservation::factory()->create([
            'therapist_id' => $colleague->staffProfile->id,
            'reservation_date' => today(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs($therapist);

        Livewire::test(MyStatsOverview::class)
            ->assertSee('Další v 14:30')
            // Two of mine, never the colleague's third.
            ->assertSeeInOrder(['Dnes mám', '2']);
    }

    public function test_the_unpaid_amount_stays_behind_the_revenue_capability(): void
    {
        $therapist = User::factory()->therapist()->create();
        $service = Service::factory()->create(['price' => 800]);

        Reservation::factory()->create([
            'therapist_id' => $therapist->staffProfile->id,
            'service_id' => $service->id,
            'reservation_date' => today(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        $this->actingAs($therapist);
        Livewire::test(MyStatsOverview::class)
            ->assertSee('Nezaplaceno')
            ->assertDontSee('800 Kč k úhradě');

        $this->actingAs(User::factory()->therapist()->revenue()->create());
        Livewire::test(MyStatsOverview::class)->assertSee('Nezaplaceno');

        // Same therapist, now allowed to see money: the sum appears.
        $therapist->grantCapability(Capability::Revenue);
        $this->actingAs($therapist->refresh());
        Livewire::test(MyStatsOverview::class)->assertSee('800 Kč k úhradě');
    }

    /**
     * Widgets are lazy, so their headings are not in the first paint — this only
     * proves the page itself is reachable and error-free for staff who, until
     * now, landed on a near-empty dashboard. The contents are asserted through
     * Livewire above.
     */
    public function test_the_dashboard_renders_for_a_pure_therapist_and_a_pure_lecturer(): void
    {
        $this->actingAs(User::factory()->therapist()->create());
        $this->get('/admin')->assertOk();

        $this->actingAs(User::factory()->lecturer()->create());
        $this->get('/admin')->assertOk();
    }

    /**
     * The stock Filament pair was filler for staff with nothing else on the page;
     * now that they have their own widgets, it goes.
     */
    public function test_staff_no_longer_see_the_stock_account_and_info_widgets(): void
    {
        foreach ([
            User::factory()->lecturer()->create(),
            User::factory()->therapist()->create(),
            User::factory()->admin()->create(),
        ] as $staff) {
            $this->actingAs($staff);

            $widgets = (new Dashboard)->getWidgets();

            $this->assertNotContains(AccountWidget::class, $widgets);
            $this->assertNotContains(FilamentInfoWidget::class, $widgets);
            // The calendar keeps its own page, and never joins the grid.
            $this->assertNotContains(ReservationCalendar::class, $widgets);
            $this->assertContains(MyStatsOverview::class, $widgets);
        }
    }
}
