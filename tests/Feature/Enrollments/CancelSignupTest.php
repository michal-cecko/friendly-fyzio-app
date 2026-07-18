<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Workshopy\Resources\WorkshopRegistrations\Pages\ListWorkshopRegistrations;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\CancelSignup;
use App\Support\Enrollments\JoinWaitlist;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CancelSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    private function confirmedRegistration(int $capacity = 5): WorkshopRegistration
    {
        $workshop = Workshop::factory()->create([
            'capacity' => $capacity,
            'price' => 900,
            'workshop_date' => today()->addWeeks(2)->toDateString(),
        ]);

        $registration = WorkshopRegistration::factory()->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $registration->payments()->create([
            'client_id' => $registration->client_id,
            'amount' => 900,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => now()->addHours(48),
        ]);

        return $registration;
    }

    public function test_service_cancels_withdraws_open_payment_and_emails_clinic_template(): void
    {
        Notification::fake();

        $registration = $this->confirmedRegistration();

        app(CancelSignup::class)($registration, true, EmailTemplateKey::EnrollmentCancelledByClinic);

        $registration->refresh();
        $this->assertSame(BookingStatus::Cancelled, $registration->status);
        $this->assertSame(0, $registration->payments()->count());

        Notification::assertSentTo(
            $registration->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::EnrollmentCancelledByClinic,
        );
    }

    public function test_service_can_skip_the_client_email(): void
    {
        Notification::fake();

        $registration = $this->confirmedRegistration();

        app(CancelSignup::class)($registration, false);

        $this->assertSame(BookingStatus::Cancelled, $registration->fresh()->status);
        Notification::assertNothingSentTo($registration->client);
    }

    public function test_cancelling_frees_the_spot_and_promotes_the_waitlist(): void
    {
        Notification::fake();

        // A full workshop with someone waiting.
        $registration = $this->confirmedRegistration(capacity: 1);
        JoinWaitlist::handle($registration->workshop, 'Náhradník Nový', 'nahradnik@example.cz');

        app(CancelSignup::class)($registration, false);

        // Auto-promotion (default on) fills the freed spot from the waitlist.
        $promoted = User::query()->where('email', 'nahradnik@example.cz')->sole();
        $this->assertTrue($registration->workshop->registrations()
            ->where('client_id', $promoted->id)
            ->whereIn('status', BookingStatus::occupying())
            ->exists());
    }

    public function test_admin_row_action_cancels_the_registration(): void
    {
        Notification::fake();

        $registration = $this->confirmedRegistration();

        Livewire::test(ListWorkshopRegistrations::class)
            ->callAction(TestAction::make('cancelSignup')->table($registration), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(BookingStatus::Cancelled, $registration->fresh()->status);
    }

    public function test_admin_row_action_hidden_for_already_cancelled(): void
    {
        $registration = WorkshopRegistration::factory()->create([
            'status' => BookingStatus::Cancelled,
        ]);

        Livewire::test(ListWorkshopRegistrations::class)
            ->assertActionHidden(TestAction::make('cancelSignup')->table($registration));
    }

    public function test_admin_bulk_action_cancels_selected_registrations(): void
    {
        Notification::fake();

        $workshop = Workshop::factory()->create(['capacity' => 10]);
        $registrations = WorkshopRegistration::factory()->count(3)->create([
            'workshop_id' => $workshop->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(ListWorkshopRegistrations::class)
            ->set('selectedTableRecords', $registrations->pluck('id')->all())
            ->callAction(TestAction::make('cancelSignups')->table()->bulk(), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $registrations->each(function (WorkshopRegistration $registration): void {
            $this->assertSame(BookingStatus::Cancelled, $registration->fresh()->status);
        });
    }
}
