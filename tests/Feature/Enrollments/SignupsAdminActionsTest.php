<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\ListOneOffEventBookings;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\ViewOneOffEvent;
use App\Filament\Support\RelationManagers\CourseSeriesEnrollmentsRelationManager;
use App\Filament\Support\RelationManagers\OneOffEventBookingsRelationManager;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\InvoiceSeries;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\User;
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

        $event = OneOffEvent::factory()->create(['capacity' => 10, 'price' => 900]);
        $bookings = OneOffEventBooking::factory()->count(2)->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        Livewire::test(ListOneOffEventBookings::class)
            ->set('selectedTableRecords', $bookings->pluck('id')->all())
            ->callAction(TestAction::make('markSignupsPaid')->table()->bulk(), [
                'method' => PaymentMethod::Qr->value,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $bookings->each(function (OneOffEventBooking $booking): void {
            $this->assertSame(PaymentStatus::Paid, $booking->fresh()->payment_status);
            $this->assertSame(1, $booking->payments()->count());
        });
    }

    public function test_generate_invoices_bulk_issues_only_for_eligible_paid_records(): void
    {
        $series = InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);

        $event = OneOffEvent::factory()->create(['capacity' => 10, 'price' => 900]);
        $paid = OneOffEventBooking::factory()->count(2)->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $unpaid = OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $selected = $paid->pluck('id')->push($unpaid->id)->all();

        Livewire::test(ListOneOffEventBookings::class)
            ->set('selectedTableRecords', $selected)
            ->callAction(TestAction::make('generateInvoices')->table()->bulk(), [
                'series_id' => $series->getKey(),
                'issued_at' => today()->toDateString(),
                'due_at' => today()->addDays(14)->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $paid->each(function (OneOffEventBooking $booking): void {
            $this->assertTrue($booking->invoice()->exists());
        });
        $this->assertFalse($unpaid->invoice()->exists());
    }

    public function test_add_participant_header_action_creates_a_signup(): void
    {
        Notification::fake();

        $event = OneOffEvent::factory()->create([
            'capacity' => 5,
            'price' => 900,
            'event_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);

        Livewire::test(OneOffEventBookingsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ViewOneOffEvent::class,
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

    public function test_add_participant_is_refused_when_the_offer_is_full(): void
    {
        Notification::fake();

        $event = OneOffEvent::factory()->create([
            'capacity' => 1,
            'price' => 900,
            'event_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);
        OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(OneOffEventBookingsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ViewOneOffEvent::class,
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
        $event = OneOffEvent::factory()->create(['capacity' => 10]);
        $bookings = OneOffEventBooking::factory()->count(2)->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(OneOffEventBookingsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ViewOneOffEvent::class,
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
}
