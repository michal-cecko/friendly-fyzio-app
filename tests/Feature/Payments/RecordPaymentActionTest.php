<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\ListLessonBookings;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\InvoiceSeries;
use App\Models\Lesson;
use App\Models\LessonBooking;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class RecordPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_recording_received_payment_on_reservation_marks_it_paid(): void
    {
        Notification::fake();

        $reservation = $this->reservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('recordPayment')->table($reservation), [
                'amount' => 800,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();

        $this->assertSame(PaymentStatus::Paid, $reservation->payment_status);
        $this->assertSame(1, $reservation->payments()->count());
        // The toggle suppresses only the client copy — the therapist notice still goes out.
        Notification::assertNotSentTo($reservation->client, PaymentReceivedNotification::class);
    }

    public function test_cash_received_payment_auto_creates_cash_receipt(): void
    {
        InvoiceSeries::factory()->receipt()->asDefault()->create(['prefix' => 'PPD']);

        $reservation = $this->reservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('recordPayment')->table($reservation), [
                'amount' => 800,
                'method' => PaymentMethod::Cash->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $payment = $reservation->payments()->sole();

        $this->assertNotNull($payment->cashReceipt()->first());
        $this->assertStringStartsWith('PPD-', $payment->cashReceipt()->first()->receipt_number);
    }

    public function test_received_off_creates_unpaid_payment_with_due_date(): void
    {
        $reservation = $this->reservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('recordPayment')->table($reservation), [
                'amount' => 800,
                'method' => PaymentMethod::Qr->value,
                'received' => false,
            ])
            ->assertHasNoActionErrors();

        $payment = $reservation->payments()->sole();

        $this->assertSame(PaymentStatus::Unpaid, $payment->status);
        $this->assertTrue($payment->due_at->isSameDay(today()->addDays(7)));
        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);
    }

    public function test_notify_toggle_sends_payment_received_email(): void
    {
        Notification::fake();

        $reservation = $this->reservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('recordPayment')->table($reservation), [
                'amount' => 800,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo($reservation->client, PaymentReceivedNotification::class);
    }

    /**
     * The toggle switches between recording money in hand and prescribing what
     * the client owes, so the helper text has to say which one is armed. Read
     * off the schema rather than the markup: the modal body is rendered client
     * side, so none of the fields appear in the component's HTML.
     */
    public function test_received_toggle_explains_both_states(): void
    {
        $reservation = $this->reservation(800);

        $component = Livewire::test(ListReservations::class)
            ->mountAction(TestAction::make('recordPayment')->table($reservation));

        $this->assertStringContainsString('Peníze už máte.', $this->receivedHelperText($component));

        $component->setActionData(['received' => false]);

        $helperText = $this->receivedHelperText($component);
        $this->assertStringContainsString('jen předepisujete', $helperText);
        $this->assertStringContainsString('splatností za 7 dní', $helperText);
    }

    /**
     * The helper text currently rendered under the "Platba již přijata" toggle
     * of the mounted action.
     */
    private function receivedHelperText(Testable $component): string
    {
        $toggle = $component
            ->instance()
            ->getSchema('mountedActionSchema0')
            ?->getComponent(fn (Component $schemaComponent): bool => $schemaComponent instanceof Toggle
                && $schemaComponent->getName() === 'received');

        $this->assertInstanceOf(Toggle::class, $toggle);

        // helperText() is sugar for a below-content Text component, so the
        // rendered child schema is where the wording actually lives.
        return (string) $toggle->getChildSchema(Field::BELOW_CONTENT_SCHEMA_KEY)?->toHtmlString();
    }

    /**
     * The Platby table lives in its own Livewire component, so recording a
     * payment from the page header has to tell it to re-query — otherwise the
     * new payment only shows up after a manual page refresh.
     */
    public function test_recording_a_payment_refreshes_the_payments_relation_manager(): void
    {
        $reservation = $this->reservation(800);

        Livewire::test(ViewReservation::class, ['record' => $reservation->getKey()])
            ->callAction(TestAction::make('recordPayment'), [
                'amount' => 800,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors()
            ->assertDispatched(PaymentsRelationManager::REFRESH_EVENT);
    }

    public function test_payments_relation_manager_listens_for_the_refresh_event(): void
    {
        $reservation = $this->reservation(800);
        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $reservation,
            'pageClass' => ViewReservation::class,
        ])
            ->assertCanSeeTableRecords([$payment])
            ->dispatch(PaymentsRelationManager::REFRESH_EVENT)
            ->assertCanSeeTableRecords([$payment])
            ->assertHasNoErrors();
    }

    public function test_action_hidden_for_paid_payable(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
        ]);

        Livewire::test(ListReservations::class)
            ->assertActionHidden(TestAction::make('recordPayment')->table($reservation));
    }

    public function test_records_payment_on_course_enrollment(): void
    {
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create(['price' => 1200])->getKey(),
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        Livewire::test(ListCourseEnrollments::class)
            ->callAction(TestAction::make('recordPayment')->table($enrollment), [
                'amount' => 1200,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(PaymentStatus::Paid, $enrollment->fresh()->payment_status);
    }

    public function test_records_payment_on_lesson_booking(): void
    {
        $booking = LessonBooking::factory()->create([
            'lesson_id' => Lesson::factory()->create(['price' => 950])->getKey(),
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        Livewire::test(ListLessonBookings::class)
            ->callAction(TestAction::make('recordPayment')->table($booking), [
                'amount' => 950,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(PaymentStatus::Paid, $booking->fresh()->payment_status);
    }

    private function reservation(int $price): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => $price])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }
}
