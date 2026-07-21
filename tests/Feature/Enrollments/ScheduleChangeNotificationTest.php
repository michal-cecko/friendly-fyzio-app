<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\EditOneOffEvent;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\NotifyScheduleChange;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleChangeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_active_bookings_and_instructor_for_an_event(): void
    {
        Notification::fake();

        $event = OneOffEvent::factory()->create(['capacity' => 10]);
        $confirmed = OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);
        $pending = OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Pending,
        ]);
        $cancelled = OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Cancelled,
        ]);

        app(NotifyScheduleChange::class)($event, ['puvodni_termin' => '1. 1. 2026, 10:00']);

        Notification::assertSentTo(
            $confirmed->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::LessonScheduleChanged,
        );
        Notification::assertSentTo($pending->client, EnrollmentTemplateNotification::class);
        Notification::assertNothingSentTo($cancelled->client);
        Notification::assertSentTo(
            $event->instructor,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::TherapistLessonScheduleChanged,
        );
    }

    public function test_course_lesson_change_notifies_the_whole_series(): void
    {
        Notification::fake();

        $series = CourseSeries::factory()->create();
        $lesson = CourseLesson::factory()->create(['series_id' => $series->getKey()]);
        $active = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);
        $cancelled = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Cancelled,
        ]);

        app(NotifyScheduleChange::class)($lesson, ['puvodni_termin' => '1. 1. 2026, 10:00']);

        Notification::assertSentTo(
            $active->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::LessonScheduleChanged,
        );
        Notification::assertNothingSentTo($cancelled->client);
        Notification::assertSentTo($lesson->instructor, EnrollmentTemplateNotification::class);
    }

    public function test_editing_an_event_date_with_the_toggle_on_notifies_participants(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $event = OneOffEvent::factory()->create([
            'capacity' => 10,
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);
        $booking = OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditOneOffEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'event_date' => today()->addWeeks(3)->toDateString(),
                'notify_participants' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertSentTo(
            $booking->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::LessonScheduleChanged,
        );
    }

    public function test_editing_an_event_with_the_toggle_off_sends_nothing(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $event = OneOffEvent::factory()->create([
            'capacity' => 10,
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);
        $booking = OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditOneOffEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'event_date' => today()->addWeeks(3)->toDateString(),
                'notify_participants' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNothingSentTo($booking->client);
    }

    public function test_editing_an_event_without_a_schedule_change_sends_nothing(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $event = OneOffEvent::factory()->create(['capacity' => 10]);
        $booking = OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditOneOffEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'name' => 'Přejmenovaná akce',
                'notify_participants' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNothingSentTo($booking->client);
    }
}
