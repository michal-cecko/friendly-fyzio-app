<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\EditLesson;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonBooking;
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

        $event = Lesson::factory()->standalone()->create(['capacity' => 10]);
        $confirmed = LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);
        $pending = LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Pending,
        ]);
        $cancelled = LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
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
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);
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

    public function test_editing_an_event_date_prompts_and_notifies_participants(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $event = Lesson::factory()->standalone()->create([
            'capacity' => 10,
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        $booking = LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditLesson::class, ['record' => $event->getKey()])
            ->fillForm(['lesson_date' => today()->addWeeks(3)->toDateString()])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionMounted('scheduleChangeNotification')
            ->callMountedAction();

        Notification::assertSentTo(
            $booking->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::LessonScheduleChanged,
        );
    }

    public function test_confirming_the_prompt_includes_the_optional_message_in_the_email(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $series = CourseSeries::factory()->create();
        $lesson = Lesson::factory()->create([
            'series_id' => $series->getKey(),
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        Livewire::test(EditLesson::class, ['record' => $lesson->getKey()])
            ->fillForm(['lesson_date' => today()->addWeeks(3)->toDateString()])
            ->call('save')
            ->assertHasNoFormErrors()
            ->setActionData(['reason' => 'Lekci posouváme o týden.'])
            ->callMountedAction();

        Notification::assertSentTo(
            $enrollment->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::LessonScheduleChanged
                && str_contains((string) ($n->tokens['zprava'] ?? ''), 'Lekci posouváme o týden.'),
        );
    }

    public function test_confirming_the_prompt_without_a_message_leaves_the_block_empty(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $series = CourseSeries::factory()->create();
        $lesson = Lesson::factory()->create([
            'series_id' => $series->getKey(),
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        Livewire::test(EditLesson::class, ['record' => $lesson->getKey()])
            ->fillForm(['lesson_date' => today()->addWeeks(3)->toDateString()])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callMountedAction();

        Notification::assertSentTo(
            $enrollment->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::LessonScheduleChanged
                && (string) ($n->tokens['zprava'] ?? '') === '',
        );
    }

    public function test_saving_a_schedule_change_without_confirming_the_prompt_sends_nothing(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $event = Lesson::factory()->standalone()->create([
            'capacity' => 10,
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);
        $booking = LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditLesson::class, ['record' => $event->getKey()])
            ->fillForm(['lesson_date' => today()->addWeeks(3)->toDateString()])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionMounted('scheduleChangeNotification');

        Notification::assertNothingSentTo($booking->client);
    }

    public function test_editing_an_event_without_a_schedule_change_does_not_prompt(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $event = Lesson::factory()->standalone()->create(['capacity' => 10]);
        $booking = LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditLesson::class, ['record' => $event->getKey()])
            ->fillForm(['name' => 'Přejmenovaná akce'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionNotMounted('scheduleChangeNotification');

        Notification::assertNothingSentTo($booking->client);
    }
}
