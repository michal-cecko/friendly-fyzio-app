<?php

namespace Tests\Feature;

use App\Filament\Clusters\Finance\Resources\CashReceipts\CashReceiptResource;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Clusters\Finance\Resources\Payments\Pages\ViewPayment;
use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ViewCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\ViewCourseLesson;
use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ViewCourse;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\ViewLessonAttendance;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\ViewOneOffEventBooking;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\OneOffEventResource;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\ViewOneOffEvent;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Clusters\Provoz\Resources\Rooms\Pages\ViewRoom;
use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\ViewService;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Models\CashReceipt;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\Invoice;
use App\Models\LessonAttendance;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InfolistResourceLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_course_infolist_links_category_and_instructor(): void
    {
        $course = Course::factory()->create();

        Livewire::test(ViewCourse::class, ['record' => $course->getKey()])
            ->assertSee(CourseCategoryResource::getUrl('view', ['record' => $course->category]), false)
            ->assertSee(UserResource::getUrl('view', ['record' => $course->instructor]), false);
    }

    public function test_course_series_infolist_links_course(): void
    {
        $series = CourseSeries::factory()->create();

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertSee(CourseResource::getUrl('view', ['record' => $series->course]), false);
    }

    public function test_course_enrollment_infolist_links_series_course_and_client(): void
    {
        $enrollment = CourseEnrollment::factory()->create();

        Livewire::test(ViewCourseEnrollment::class, ['record' => $enrollment->getKey()])
            ->assertSee(CourseSeriesResource::getUrl('view', ['record' => $enrollment->series]), false)
            ->assertSee(CourseResource::getUrl('view', ['record' => $enrollment->series->course]), false)
            ->assertSee(ClientResource::getUrl('view', ['record' => $enrollment->client]), false);
    }

    public function test_lesson_attendance_infolist_links_course_and_client(): void
    {
        $attendance = LessonAttendance::factory()->create();

        Livewire::test(ViewLessonAttendance::class, ['record' => $attendance->getKey()])
            ->assertSee(CourseResource::getUrl('view', ['record' => $attendance->lesson->series->course]), false)
            ->assertSee(ClientResource::getUrl('view', ['record' => $attendance->enrollment->client]), false);
    }

    public function test_one_off_event_infolist_links_related_records(): void
    {
        $event = OneOffEvent::factory()->create();

        Livewire::test(ViewOneOffEvent::class, ['record' => $event->getKey()])
            ->assertSee(EventCategoryResource::getUrl('view', ['record' => $event->category]), false)
            ->assertSee(UserResource::getUrl('view', ['record' => $event->instructor]), false)
            ->assertSee(RoomResource::getUrl('view', ['record' => $event->room]), false);
    }

    public function test_course_lesson_infolist_links_related_records(): void
    {
        $lesson = CourseLesson::factory()->create();

        Livewire::test(ViewCourseLesson::class, ['record' => $lesson->getKey()])
            ->assertSee(CourseSeriesResource::getUrl('view', ['record' => $lesson->series]), false)
            ->assertSee(CourseResource::getUrl('view', ['record' => $lesson->series->course]), false)
            ->assertSee(UserResource::getUrl('view', ['record' => $lesson->instructor]), false)
            ->assertSee(RoomResource::getUrl('view', ['record' => $lesson->room]), false);
    }

    public function test_one_off_event_booking_infolist_links_event_and_client(): void
    {
        $booking = OneOffEventBooking::factory()->create();

        Livewire::test(ViewOneOffEventBooking::class, ['record' => $booking->getKey()])
            ->assertSee(OneOffEventResource::getUrl('view', ['record' => $booking->event]), false)
            ->assertSee(ClientResource::getUrl('view', ['record' => $booking->client]), false);
    }

    public function test_reservation_infolist_links_therapist_to_view_and_confirmer(): void
    {
        $therapistProfile = StaffProfile::factory()->create();
        $confirmer = User::factory()->admin()->create();

        $reservation = Reservation::factory()->create([
            'therapist_id' => $therapistProfile->id,
            'confirmed_by_id' => $confirmer->id,
        ]);

        Livewire::test(ViewReservation::class, ['record' => $reservation->getKey()])
            ->assertSee(UserResource::getUrl('view', ['record' => $therapistProfile->user_id]), false)
            ->assertSee(UserResource::getUrl('view', ['record' => $confirmer]), false)
            ->assertDontSee(UserResource::getUrl('edit', ['record' => $therapistProfile->user_id]), false);
    }

    public function test_service_infolist_links_category_rooms_and_therapists(): void
    {
        $service = Service::factory()->create();
        $room = Room::factory()->create();
        $therapistProfile = StaffProfile::factory()->create();

        $service->rooms()->attach($room);
        $service->therapists()->attach($therapistProfile);

        Livewire::test(ViewService::class, ['record' => $service->getKey()])
            ->assertSee(RoomResource::getUrl('view', ['record' => $room]), false)
            ->assertSee(UserResource::getUrl('view', ['record' => $therapistProfile->user_id]), false);
    }

    public function test_room_infolist_links_each_service(): void
    {
        $room = Room::factory()->create();
        $service = Service::factory()->create();

        $room->services()->attach($service);

        Livewire::test(ViewRoom::class, ['record' => $room->getKey()])
            ->assertSee(ServiceResource::getUrl('view', ['record' => $service]), false);
    }

    public function test_payment_infolist_links_client(): void
    {
        $payment = Payment::factory()->create();

        Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
            ->assertSee(ClientResource::getUrl('view', ['record' => $payment->client]), false);
    }

    public function test_invoice_infolist_links_each_payment_and_cash_receipt(): void
    {
        $invoice = Invoice::factory()->create();
        $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);
        $cashReceipt = CashReceipt::factory()->create(['invoice_id' => $invoice->id]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getKey()])
            ->assertSee(PaymentResource::getUrl('view', ['record' => $payment]), false)
            ->assertSee(CashReceiptResource::getUrl('view', ['record' => $cashReceipt]), false);
    }
}
