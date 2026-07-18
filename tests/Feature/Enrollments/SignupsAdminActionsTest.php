<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Pages\ViewOneTimeLesson;
use App\Filament\Clusters\Workshopy\Resources\WorkshopRegistrations\Pages\ListWorkshopRegistrations;
use App\Filament\Clusters\Workshopy\Resources\Workshops\Pages\ViewWorkshop;
use App\Filament\Support\RelationManagers\CourseSeriesEnrollmentsRelationManager;
use App\Filament\Support\RelationManagers\OneTimeLessonBookingsRelationManager;
use App\Filament\Support\RelationManagers\WorkshopSignupsRelationManager;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\InvoiceSeries;
use App\Models\OneTimeLesson;
use App\Models\OneTimeLessonBooking;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $workshop = Workshop::factory()->create(['capacity' => 10, 'price' => 900]);
        $registrations = WorkshopRegistration::factory()->count(2)->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        Livewire::test(ListWorkshopRegistrations::class)
            ->set('selectedTableRecords', $registrations->pluck('id')->all())
            ->callAction(TestAction::make('markSignupsPaid')->table()->bulk(), [
                'method' => PaymentMethod::Qr->value,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $registrations->each(function (WorkshopRegistration $registration): void {
            $this->assertSame(PaymentStatus::Paid, $registration->fresh()->payment_status);
            $this->assertSame(1, $registration->payments()->count());
        });
    }

    public function test_generate_invoices_bulk_issues_only_for_eligible_paid_records(): void
    {
        $series = InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);

        $workshop = Workshop::factory()->create(['capacity' => 10, 'price' => 900]);
        $paid = WorkshopRegistration::factory()->count(2)->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $unpaid = WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $selected = $paid->pluck('id')->push($unpaid->id)->all();

        Livewire::test(ListWorkshopRegistrations::class)
            ->set('selectedTableRecords', $selected)
            ->callAction(TestAction::make('generateInvoices')->table()->bulk(), [
                'series_id' => $series->getKey(),
                'issued_at' => today()->toDateString(),
                'due_at' => today()->addDays(14)->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $paid->each(function (WorkshopRegistration $registration): void {
            $this->assertTrue($registration->invoice()->exists());
        });
        $this->assertFalse($unpaid->invoice()->exists());
    }

    public function test_add_participant_header_action_creates_a_signup(): void
    {
        Notification::fake();

        $workshop = Workshop::factory()->create([
            'capacity' => 5,
            'price' => 900,
            'workshop_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);

        Livewire::test(WorkshopSignupsRelationManager::class, [
            'ownerRecord' => $workshop,
            'pageClass' => ViewWorkshop::class,
        ])
            ->callAction(TestAction::make('addParticipant')->table(), [
                'name' => 'Nový Účastník',
                'email' => 'novy@example.cz',
                'phone' => '+420604123456',
                'note' => null,
            ])
            ->assertHasNoActionErrors();

        $client = User::query()->where('email', 'novy@example.cz')->sole();
        $registration = $workshop->registrations()->where('client_id', $client->id)->sole();

        $this->assertSame(BookingStatus::Confirmed, $registration->status);
        $this->assertSame(1, $registration->payments()->count());
    }

    public function test_add_participant_is_refused_when_the_offer_is_full(): void
    {
        Notification::fake();

        $workshop = Workshop::factory()->create([
            'capacity' => 1,
            'price' => 900,
            'workshop_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);
        WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(WorkshopSignupsRelationManager::class, [
            'ownerRecord' => $workshop,
            'pageClass' => ViewWorkshop::class,
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

    public function test_signups_relation_manager_lists_the_offer_registrations(): void
    {
        $workshop = Workshop::factory()->create(['capacity' => 10]);
        $registrations = WorkshopRegistration::factory()->count(2)->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(WorkshopSignupsRelationManager::class, [
            'ownerRecord' => $workshop,
            'pageClass' => ViewWorkshop::class,
        ])
            ->assertCanSeeTableRecords($registrations);
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

    public function test_one_time_lesson_bookings_relation_manager_lists_records(): void
    {
        $lesson = OneTimeLesson::factory()->create(['capacity' => 10]);
        $bookings = OneTimeLessonBooking::factory()->count(2)->create([
            'lesson_id' => $lesson->getKey(),
        ]);

        Livewire::test(OneTimeLessonBookingsRelationManager::class, [
            'ownerRecord' => $lesson,
            'pageClass' => ViewOneTimeLesson::class,
        ])
            ->assertCanSeeTableRecords($bookings);
    }
}
