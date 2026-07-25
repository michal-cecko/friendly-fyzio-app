<?php

namespace Tests\Feature\Emails;

use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Support\Actions\SendEmailAction;
use App\Listeners\LogSentEmail;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\CustomEmailNotification;
use App\Notifications\ReservationTemplateNotification;
use App\Support\Emails\CopyRecipients;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Staff can put a hidden copy on any e-mail they send by hand — accounting, a
 * colleague, their own archive — whether they resend a CMS template or compose
 * a message from scratch. The copy is part of the record: it shows up in the
 * activity log next to the primary recipient.
 */
class SendEmailCopiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    private function reservation(): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
        ]);
    }

    public function test_a_resent_template_carries_the_copies(): void
    {
        Notification::fake();

        $reservation = $this->reservation();

        Livewire::test(ViewReservation::class, ['record' => $reservation->getKey()])
            ->callAction(SendEmailAction::class, [
                'mode' => 'template',
                'template_key' => EmailTemplateKey::ReservationConfirmed->value,
                'cc' => ['kolega@friendlyfyzio.cz'],
                'bcc' => ['archiv@friendlyfyzio.cz'],
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo(
            $reservation->client,
            ReservationTemplateNotification::class,
            function (ReservationTemplateNotification $notification) use ($reservation): bool {
                $mail = $notification->toMail($reservation->client);

                return $notification->copies?->bcc === ['archiv@friendlyfyzio.cz']
                    && $mail->cc === [['kolega@friendlyfyzio.cz', null]]
                    && $mail->bcc === [['archiv@friendlyfyzio.cz', null]];
            },
        );
    }

    public function test_a_custom_message_still_carries_the_copies(): void
    {
        Notification::fake();

        $reservation = $this->reservation();

        Livewire::test(ViewReservation::class, ['record' => $reservation->getKey()])
            ->callAction(SendEmailAction::class, [
                'mode' => 'custom',
                'recipient' => 'klient@example.com',
                'subject' => 'Dobrý den',
                'body' => '<p>Zpráva</p>',
                'bcc' => ['archiv@friendlyfyzio.cz'],
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentOnDemand(
            CustomEmailNotification::class,
            fn (CustomEmailNotification $notification): bool => $notification->copies?->bcc === ['archiv@friendlyfyzio.cz'],
        );
    }

    public function test_sending_without_copies_leaves_the_mail_untouched(): void
    {
        $reservation = $this->reservation();

        $mail = (new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationConfirmed))
            ->toMail($reservation->client);

        $this->assertSame([], $mail->cc);
        $this->assertSame([], $mail->bcc);
    }

    public function test_an_invalid_copy_address_is_rejected(): void
    {
        $reservation = $this->reservation();

        Livewire::test(ViewReservation::class, ['record' => $reservation->getKey()])
            ->callAction(SendEmailAction::class, [
                'mode' => 'template',
                'template_key' => EmailTemplateKey::ReservationConfirmed->value,
                'bcc' => ['tohle-neni-e-mail'],
            ])
            ->assertHasActionErrors(['bcc.0']);
    }

    public function test_the_activity_log_records_the_copies(): void
    {
        $reservation = $this->reservation();

        $notification = new ReservationTemplateNotification(
            $reservation,
            EmailTemplateKey::ReservationConfirmed,
            [],
            new CopyRecipients(bcc: ['archiv@friendlyfyzio.cz']),
        );

        app(LogSentEmail::class)->handle(
            new NotificationSent($reservation->client, $notification, 'mail'),
        );

        $activity = Activity::query()->where('event', 'email_sent')->latest('id')->firstOrFail();

        $this->assertSame(['archiv@friendlyfyzio.cz'], $activity->properties['bcc'] ?? null);
        $this->assertArrayNotHasKey('cc', $activity->properties->all());
    }

    public function test_an_anonymous_copy_holder_is_not_mistaken_for_the_recipient(): void
    {
        $reservation = $this->reservation();

        $notification = new ReservationTemplateNotification(
            $reservation,
            EmailTemplateKey::ReservationConfirmed,
            [],
            new CopyRecipients(cc: ['kolega@friendlyfyzio.cz']),
        );

        app(LogSentEmail::class)->handle(
            new NotificationSent(
                (new AnonymousNotifiable)->route('mail', 'klient@example.com'),
                $notification,
                'mail',
            ),
        );

        $activity = Activity::query()->where('event', 'email_sent')->latest('id')->firstOrFail();

        $this->assertSame(['klient@example.com'], $activity->properties['recipients']);
        $this->assertSame(['kolega@friendlyfyzio.cz'], $activity->properties['cc']);
    }
}
