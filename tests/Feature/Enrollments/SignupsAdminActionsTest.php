<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\ListLessonBookings;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ViewLesson;
use App\Filament\Support\RelationManagers\CourseSeriesEnrollmentsRelationManager;
use App\Filament\Support\RelationManagers\LessonBookingsRelationManager;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\InvoiceSeries;
use App\Models\Lesson;
use App\Models\LessonBooking;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SignupsAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_mark_paid_bulk_settles_each_selected_record(): void
    {
        Notification::fake();

        $event = Lesson::factory()->standalone()->create(['capacity' => 10, 'price' => 900]);
        $bookings = LessonBooking::factory()->count(2)->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        Livewire::test(ListLessonBookings::class)
            ->set('selectedTableRecords', $bookings->pluck('id')->all())
            ->callAction(TestAction::make('markSignupsPaid')->table()->bulk(), [
                'method' => PaymentMethod::Qr->value,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $bookings->each(function (LessonBooking $booking): void {
            $this->assertSame(PaymentStatus::Paid, $booking->fresh()->payment_status);
            $this->assertSame(1, $booking->payments()->count());
        });
    }

    public function test_generate_invoices_bulk_issues_only_for_eligible_paid_records(): void
    {
        // Issuing an invoice renders its PDF through Gotenberg over HTTP. Fake
        // the service, the way the invoice tests do — otherwise the suite only
        // passes where a gotenberg host happens to resolve.
        Http::fake([config('services.gotenberg.url').'/*' => Http::response('%PDF-1.7 fake')]);

        $series = InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);

        $event = Lesson::factory()->standalone()->create(['capacity' => 10, 'price' => 900]);
        $paid = LessonBooking::factory()->count(2)->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $unpaid = LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $selected = $paid->pluck('id')->push($unpaid->id)->all();

        Livewire::test(ListLessonBookings::class)
            ->set('selectedTableRecords', $selected)
            ->callAction(TestAction::make('generateInvoices')->table()->bulk(), [
                'series_id' => $series->getKey(),
                'issued_at' => today()->toDateString(),
                'due_at' => today()->addDays(14)->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $paid->each(function (LessonBooking $booking): void {
            $this->assertTrue($booking->invoice()->exists());
        });
        $this->assertFalse($unpaid->invoice()->exists());
    }

    public function test_add_participant_header_action_creates_a_signup(): void
    {
        Notification::fake();

        $event = Lesson::factory()->standalone()->create([
            'capacity' => 5,
            'price' => 900,
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);

        Livewire::test(LessonBookingsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ViewLesson::class,
        ])
            ->callAction(TestAction::make('addParticipant')->table(), [
                'name' => 'Nový Účastník',
                'email' => 'novy@example.cz',
                'phone' => '+420604123456',
                'note' => null,
            ])
            ->assertHasNoActionErrors();

        $client = User::query()->where('email', 'novy@example.cz')->sole();
        $booking = $event->bookings()->where('client_id', $client->id)->sole();

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(1, $booking->payments()->count());
    }

    /**
     * A full offer disables the button up front and says why on hover, instead
     * of letting an admin fill the whole form only to be refused on submit.
     */
    public function test_add_participant_button_is_disabled_and_explains_a_full_offer(): void
    {
        $event = Lesson::factory()->standalone()->create([
            'capacity' => 1,
            'price' => 900,
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);

        $component = Livewire::test(LessonBookingsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ViewLesson::class,
        ]);

        $component->assertActionEnabled(TestAction::make('addParticipant')->table());

        LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        $component = Livewire::test(LessonBookingsRelationManager::class, [
            'ownerRecord' => $event->refresh(),
            'pageClass' => ViewLesson::class,
        ]);

        $component->assertActionDisabled(TestAction::make('addParticipant')->table());

        $action = $component->instance()->getTable()->getAction('addParticipant');

        $this->assertStringContainsString('Kapacita je naplněná', (string) $action?->getTooltip());
    }

    public function test_add_participant_is_refused_when_the_offer_is_full(): void
    {
        Notification::fake();

        $event = Lesson::factory()->standalone()->create([
            'capacity' => 1,
            'price' => 900,
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);
        LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(LessonBookingsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ViewLesson::class,
        ])
            ->callAction(TestAction::make('addParticipant')->table(), [
                'name' => 'Pozdní Zájemce',
                'email' => 'pozdni@example.cz',
                'phone' => null,
                'note' => null,
            ])
            ->assertHasNoActionErrors();

        $this->assertFalse(User::query()->where('email', 'pozdni@example.cz')->exists());
    }

    public function test_bookings_relation_manager_lists_the_event_signups(): void
    {
        $event = Lesson::factory()->standalone()->create(['capacity' => 10]);
        $bookings = LessonBooking::factory()->count(2)->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(LessonBookingsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ViewLesson::class,
        ])
            ->assertCanSeeTableRecords($bookings);
    }

    public function test_course_series_enrollments_relation_manager_lists_records(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);
        $enrollments = CourseEnrollment::factory()->count(2)->create([
            'series_id' => $series->getKey(),
        ]);

        Livewire::test(CourseSeriesEnrollmentsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->assertCanSeeTableRecords($enrollments);
    }

    /**
     * The whole row links to the enrollment's own page, so an admin does not
     * have to aim for the "Detail" row action.
     */
    public function test_clicking_an_enrollment_row_opens_its_detail_page(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);
        $enrollment = CourseEnrollment::factory()->create(['series_id' => $series->getKey()]);

        $table = Livewire::test(CourseSeriesEnrollmentsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->instance()
            ->getTable();

        $this->assertSame(
            CourseEnrollmentResource::getUrl('view', ['record' => $enrollment]),
            $table->getRecordUrl($enrollment),
        );
    }
}
