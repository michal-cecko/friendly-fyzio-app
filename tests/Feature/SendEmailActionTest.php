<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ViewCourseEnrollment;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Filament\Clusters\System\Resources\Users\Pages\ViewUser;
use App\Models\CourseEnrollment;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ClientAccountCreatedNotification;
use App\Notifications\CustomEmailNotification;
use App\Notifications\EnrollmentTemplateNotification;
use Database\Seeders\EmailTemplateSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SendEmailActionTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->sender = User::factory()->admin()->create();
        $this->actingAs($this->sender);
    }

    public function test_custom_mode_sends_free_form_email_to_typed_recipient_with_cc_and_bcc(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending,
            'reservation_date' => today()->addWeeks(2),
        ]);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('sendEmail')->table($reservation), [
                'mode' => 'custom',
                'recipient' => 'someone@example.com',
                'cc' => ['cc@example.com'],
                'bcc' => ['bcc@example.com'],
                'subject' => 'Ahoj',
                'body' => '<p>Zpráva</p>',
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentOnDemand(
            CustomEmailNotification::class,
            function (CustomEmailNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($reservation): bool {
                return $notifiable->routes['mail'] === 'someone@example.com'
                    && $notification->emailSubject === 'Ahoj'
                    && $notification->cc === ['cc@example.com']
                    && $notification->bcc === ['bcc@example.com']
                    && $notification->replyToAddress === $this->sender->email
                    && $notification->replyToName === $this->sender->name
                    && $notification->record?->is($reservation);
            },
        );
    }

    public function test_template_mode_on_enrollment_sends_enrollment_notification_to_client(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);

        $enrollment = CourseEnrollment::factory()->create();

        Livewire::test(ViewCourseEnrollment::class, ['record' => $enrollment->getKey()])
            ->callAction(TestAction::make('sendEmail'), [
                'mode' => 'template',
                'template_key' => EmailTemplateKey::CourseEnrollmentReceived->value,
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo(
            $enrollment->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::CourseEnrollmentReceived,
        );
    }

    public function test_template_mode_on_user_resends_account_created_welcome(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->admin()->create();

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction(TestAction::make('sendEmail'), [
                'mode' => 'template',
                'template_key' => EmailTemplateKey::AccountCreated->value,
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo($user, ClientAccountCreatedNotification::class);
    }
}
