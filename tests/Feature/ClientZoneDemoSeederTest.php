<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Models\User;
use Database\Seeders\ClientZoneDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientZoneDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduce the prerequisite chain from {@see DatabaseSeeder}:
     * the demo customer needs the foundation plus DemoSeeder's services, courses
     * and workshops, and at least one therapist to instruct the seeded lessons.
     */
    protected function seedDemoClient(): void
    {
        $this->seed(FoundationSeeder::class);
        User::factory()->therapist()->create(['email' => 'therapist@friendly-fyzio.test']);
        $this->seed(DemoSeeder::class);
        $this->seed(ClientZoneDemoSeeder::class);
    }

    protected function demoClient(): User
    {
        return User::query()->where('email', ClientZoneDemoSeeder::EMAIL)->firstOrFail();
    }

    public function test_seeds_a_bookable_verified_customer(): void
    {
        $this->seedDemoClient();

        $client = $this->demoClient();

        $this->assertTrue($client->isCustomer());
        $this->assertNotNull($client->email_verified_at);
        $this->assertNull($client->deactivated_at);
        $this->assertNotNull($client->clientProfile);
    }

    public function test_seeds_both_past_and_future_reservations(): void
    {
        $this->seedDemoClient();

        $reservations = $this->demoClient()->reservations()->get();

        $this->assertSame(4, $reservations->where('reservation_date', '<', today())->count());
        $this->assertSame(3, $reservations->where('reservation_date', '>=', today())->count());
    }

    public function test_enrolls_the_client_in_a_running_and_a_future_course(): void
    {
        $this->seedDemoClient();

        $enrollments = $this->demoClient()->courseEnrollments()->with('series')->get();

        $running = $enrollments->first(fn ($enrollment): bool => $enrollment->series->start_date->isPast()
            && $enrollment->series->end_date->isFuture());
        $future = $enrollments->first(fn ($enrollment): bool => $enrollment->series->start_date->isFuture());

        $this->assertNotNull($running, 'The client should attend a course that is currently running.');
        $this->assertSame(CourseEnrollmentStatus::Active, $running->status);
        $this->assertNotNull($future, 'The client should be enrolled in an upcoming course run.');
    }

    public function test_books_a_past_attended_and_a_future_one_off_lesson(): void
    {
        $this->seedDemoClient();

        $bookings = $this->demoClient()->lessonBookings()->with('lesson')->get();

        $past = $bookings->first(fn (LessonBooking $booking): bool => $booking->lesson->lesson_date->isPast());
        $future = $bookings->first(fn (LessonBooking $booking): bool => $booking->lesson->lesson_date->isFuture());

        $this->assertNotNull($past, 'The client should have attended a past one-off lesson.');
        $this->assertSame(PaymentStatus::Paid, $past->payment_status);
        $this->assertTrue(
            LessonAttendance::query()->where('booking_id', $past->getKey())->value('attended'),
            'The past one-off lesson should be marked attended.',
        );

        $this->assertNotNull($future, 'The client should have joined a future one-off lesson.');
        $this->assertSame(BookingStatus::Confirmed, $future->status);
    }

    public function test_is_idempotent(): void
    {
        $this->seedDemoClient();
        $this->seed(ClientZoneDemoSeeder::class);

        $client = $this->demoClient();

        $this->assertSame(1, User::query()->where('email', ClientZoneDemoSeeder::EMAIL)->count());
        $this->assertSame(7, $client->reservations()->count());
        $this->assertSame(2, $client->courseEnrollments()->count());
        $this->assertSame(2, $client->lessonBookings()->count());
    }
}
