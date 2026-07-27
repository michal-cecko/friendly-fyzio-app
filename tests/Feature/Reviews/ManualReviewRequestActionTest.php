<?php

namespace Tests\Feature\Reviews;

use App\Enums\ReservationStatus;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\ListLessonBookings;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Models\Lesson;
use App\Models\LessonBooking;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReviewRequestNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ManualReviewRequestActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_admin_can_send_manual_request_for_past_confirmed_reservation(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'reservation_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListReservations::class)
            ->callAction(
                TestAction::make('sendReviewRequest')->table($reservation),
                data: ['message' => 'Děkujeme za návštěvu!'],
            )
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('review_requests', [
            'user_id' => $reservation->client_id,
            'reviewable_type' => 'reservation',
            'reviewable_id' => $reservation->getKey(),
            'channel' => 'manual',
        ]);
        Notification::assertSentTo($reservation->client, ReviewRequestNotification::class);
    }

    public function test_admin_can_send_manual_request_for_past_event_booking(): void
    {
        Notification::fake();

        $event = Lesson::factory()->create(['lesson_date' => now()->subDays(2)->toDateString()]);
        $booking = LessonBooking::factory()->create(['lesson_id' => $event->getKey()]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListLessonBookings::class)
            ->callAction(
                TestAction::make('sendReviewRequest')->table($booking),
                data: [],
            )
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('review_requests', [
            'user_id' => $booking->client_id,
            'reviewable_type' => 'lesson',
            'reviewable_id' => $event->getKey(),
            'channel' => 'manual',
        ]);
        Notification::assertSentTo($booking->client, ReviewRequestNotification::class);
    }

    public function test_action_is_hidden_for_a_future_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'reservation_date' => now()->addWeek()->toDateString(),
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListReservations::class)
            ->assertActionHidden(TestAction::make('sendReviewRequest')->table($reservation));
    }

    public function test_action_is_hidden_for_a_future_event_booking(): void
    {
        $event = Lesson::factory()->create(['lesson_date' => now()->addWeek()->toDateString()]);
        $booking = LessonBooking::factory()->create(['lesson_id' => $event->getKey()]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListLessonBookings::class)
            ->assertActionHidden(TestAction::make('sendReviewRequest')->table($booking));
    }
}
