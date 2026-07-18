<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Workshopy\Resources\Workshops\Pages\EditWorkshop;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\OneTimeLessonBooking;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
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

    public function test_notifies_active_registrations_and_instructor_for_a_workshop(): void
    {
        Notification::fake();

        $workshop = Workshop::factory()->create(['capacity' => 10]);
        $confirmed = WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);
        $cancelled = WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Cancelled,
        ]);

        app(NotifyScheduleChange::class)($workshop, ['puvodni_termin' => '1. 1. 2026, 10:00']);

        Notification::assertSentTo(
            $confirmed->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::LessonScheduleChanged,
        );
        Notification::assertNothingSentTo($cancelled->client);
        Notification::assertSentTo(
            $workshop->instructor,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::TherapistLessonScheduleChanged,
        );
    }

    public function test_notifies_active_bookings_for_a_one_time_lesson(): void
    {
        Notification::fake();

        $lesson = OneTimeLesson::factory()->create(['capacity' => 10]);
        $active = OneTimeLessonBooking::factory()->create([
            'lesson_id' => $lesson->getKey(),
            'status' => BookingStatus::Pending,
        ]);
        $cancelled = OneTimeLessonBooking::factory()->create([
            'lesson_id' => $lesson->getKey(),
            'status' => BookingStatus::Cancelled,
        ]);

        app(NotifyScheduleChange::class)($lesson, ['puvodni_termin' => '1. 1. 2026, 10:00']);

        Notification::assertSentTo($active->client, EnrollmentTemplateNotification::class);
        Notification::assertNothingSentTo($cancelled->client);
        Notification::assertSentTo($lesson->instructor, EnrollmentTemplateNotification::class);
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

    public function test_editing_a_workshop_date_with_the_toggle_on_notifies_participants(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $workshop = Workshop::factory()->create([
            'capacity' => 10,
            'workshop_date' => today()->addWeeks(2)->toDateString(),
        ]);
        $registration = WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditWorkshop::class, ['record' => $workshop->getKey()])
            ->fillForm([
                'workshop_date' => today()->addWeeks(3)->toDateString(),
                'notify_participants' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertSentTo(
            $registration->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::LessonScheduleChanged,
        );
    }

    public function test_editing_a_workshop_with_the_toggle_off_sends_nothing(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $workshop = Workshop::factory()->create([
            'capacity' => 10,
            'workshop_date' => today()->addWeeks(2)->toDateString(),
        ]);
        $registration = WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditWorkshop::class, ['record' => $workshop->getKey()])
            ->fillForm([
                'workshop_date' => today()->addWeeks(3)->toDateString(),
                'notify_participants' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNothingSentTo($registration->client);
    }

    public function test_editing_a_workshop_without_a_schedule_change_sends_nothing(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $workshop = Workshop::factory()->create(['capacity' => 10]);
        $registration = WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(EditWorkshop::class, ['record' => $workshop->getKey()])
            ->fillForm([
                'name' => 'Přejmenovaný workshop',
                'notify_participants' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNothingSentTo($registration->client);
    }
}
