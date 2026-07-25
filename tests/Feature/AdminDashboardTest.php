<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\Problems;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\ProblemsWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\UpcomingReservationsWidget;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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
            [ProblemsWidget::class],
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

    public function test_problems_page_is_admin_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(Problems::canAccess());

        $this->actingAs(User::factory()->therapist()->create());
        $this->assertFalse(Problems::canAccess());

        $this->actingAs(User::factory()->customer()->create());
        $this->assertFalse(Problems::canAccess());
    }

    public function test_problems_page_nav_badge_reflects_conflict_count(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->assertFalse(Problems::shouldRegisterNavigation());

        $room = Room::factory()->create();
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:00', 'end_time' => '10:00']);
        Reservation::factory()->create(['reservation_date' => today()->addDay(), 'room_id' => $room->id, 'status' => ReservationStatus::Confirmed, 'start_time' => '09:30', 'end_time' => '10:30']);
        Once::flush();

        // Same room, two (different, factory-random) therapists → one room conflict.
        $this->assertTrue(Problems::shouldRegisterNavigation());
        $this->assertSame('1', Problems::getNavigationBadge());
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
}
