<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ListClients;
use App\Filament\Clusters\Provoz\Resources\Payments\Pages\ListPayments;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\EditReservation;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReservationStornoPaymentNotification;
use App\Notifications\ReservationTemplateNotification;
use App\Support\Payments\PaymentNote;
use App\Support\Payments\QrPlatba;
use App\Support\Reservations\CreateReservationFromWizard;
use App\Support\Reservations\DeactivatedClientException;
use App\Support\Reservations\ReservationBookingData;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-08 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'client_id' => User::factory()->customer(),
            'status' => ReservationStatus::Pending,
            'confirmation_sent_at' => null,
            'confirmed_at' => null,
            // Default: well outside the 24h storno window.
            'reservation_date' => '2026-07-20',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $attributes));
    }

    /**
     * A confirmed reservation on a priced service — cancelling it triggers the storno
     * decision (unless $price is 0).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function stornoReservation(int $price = 1000, array $attributes = []): Reservation
    {
        $service = Service::factory()->create(['price' => $price]);

        return $this->reservation(array_merge([
            'service_id' => $service->id,
            'status' => ReservationStatus::Confirmed,
            'reservation_date' => '2026-07-08',
            'start_time' => '15:00',
            'end_time' => '16:00',
        ], $attributes));
    }

    // --- Confirm flow (via the single manage link) ---------------------------

    public function test_opening_the_manage_link_shows_the_page_without_mutating(): void
    {
        $reservation = $this->reservation();

        $this->get($reservation->manageUrl())
            ->assertOk()
            ->assertSee('Potvrdit rezervaci');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertNull($reservation->confirmed_at);
    }

    public function test_posting_confirm_confirms_and_emails_once(): void
    {
        Notification::fake();

        $reservation = $this->reservation();

        // Confirm, then a double-submit (already Confirmed) must not re-send.
        $this->post($reservation->manageUrl(), ['action' => 'confirm'])->assertRedirect();
        $this->post($reservation->manageUrl(), ['action' => 'confirm'])->assertRedirect();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNotNull($reservation->confirmed_at);

        Notification::assertSentToTimes($reservation->client, ReservationTemplateNotification::class, 1);
        Notification::assertSentTo($reservation->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationConfirmed;
        });
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $reservation = $this->reservation();

        $this->get($reservation->manageUrl().'tampered')->assertForbidden();

        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
    }

    public function test_cancelled_reservation_shows_a_notice_and_cannot_be_confirmed(): void
    {
        Notification::fake();

        $reservation = $this->reservation(['status' => ReservationStatus::Cancelled]);

        $this->get($reservation->manageUrl())
            ->assertOk()
            ->assertSee('zrušena');

        $this->post($reservation->manageUrl(), ['action' => 'confirm'])->assertRedirect();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_pending_email_carries_the_signed_manage_link(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $client = User::factory()->customer()->create(['name' => 'Jana Nováková']);
        $reservation = $this->reservation(['client_id' => $client->id]);

        $mail = (new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationPending))->toMail($client);
        $html = $mail->viewData['html'] ?? '';

        $this->assertSame('emails.rendered', $mail->view);
        $this->assertStringContainsString('rezervace/spravovat/'.$reservation->id, $html);
        $this->assertStringContainsString('signature=', $html);
        $this->assertStringContainsString('Potvrdit rezervaci', $html);
        $this->assertStringContainsString('Jana,', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    // --- Free cancel (while Pending) -----------------------------------------

    public function test_customer_free_cancels_while_pending(): void
    {
        Notification::fake();

        $reservation = $this->reservation();

        $this->get($reservation->manageUrl())
            ->assertOk()
            ->assertSee('Zrušit rezervaci');

        $this->post($reservation->manageUrl(), ['action' => 'cancel'])->assertRedirect();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Zrušeno klientem', $reservation->cancellation_reason);

        Notification::assertSentTo($reservation->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationCancelled;
        });
    }

    public function test_pending_within_window_faces_storno_choice(): void
    {
        Notification::fake();

        // Unconfirmed but only a few hours out (inside the storno window) → no free cancel:
        // the storno choice applies, closing the "never confirm, cancel free" loophole.
        $service = Service::factory()->create(['price' => 1000]);
        $reservation = $this->reservation([
            'service_id' => $service->id,
            'reservation_date' => '2026-07-08',
            'start_time' => '15:00',
            'end_time' => '16:00',
        ]);

        $this->get($reservation->manageUrl())
            ->assertOk()
            ->assertSee('zaplatím storno poplatek');

        $this->post($reservation->manageUrl(), ['action' => 'cancel'])->assertRedirect();

        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_confirmed_cannot_free_cancel(): void
    {
        Notification::fake();

        $reservation = $this->stornoReservation();

        // Once confirmed (and a fee applies) the free-cancel action does nothing.
        $this->post($reservation->manageUrl(), ['action' => 'cancel'])->assertRedirect();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_confirmed_free_cancels_when_there_is_no_fee(): void
    {
        Notification::fake();

        // Zero-price service → no storno fee to enforce → a confirmed cancel is free.
        $reservation = $this->stornoReservation(0);

        $this->get($reservation->manageUrl())
            ->assertOk()
            ->assertSee('Zrušit rezervaci');

        $this->post($reservation->manageUrl(), ['action' => 'cancel'])->assertRedirect();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        Notification::assertSentTo($reservation->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationCancelled;
        });
    }

    // --- Storno decision (Confirmed, fee applies) ----------------------------

    public function test_confirmed_shows_storno_options(): void
    {
        $reservation = $this->stornoReservation(1000);

        $this->get($reservation->manageUrl())
            ->assertOk()
            ->assertSee('500 Kč')
            ->assertSee('zaplatím storno poplatek')
            ->assertSee('potvrzení od lékaře')
            ->assertSee('storno neuhradím');
    }

    public function test_confirmed_far_out_still_faces_storno_choice(): void
    {
        Notification::fake();

        // Visit ~12 days away (well outside the old 24h window) — status, not time, decides.
        $service = Service::factory()->create(['price' => 1000]);
        $reservation = $this->reservation([
            'service_id' => $service->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->get($reservation->manageUrl())
            ->assertOk()
            ->assertSee('zaplatím storno poplatek')
            ->assertDontSee('Zrušit rezervaci');

        $this->post($reservation->manageUrl(), ['action' => 'cancel'])->assertRedirect();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_storno_pay_cancels_creates_payment_and_emails_qr(): void
    {
        Notification::fake();

        $reservation = $this->stornoReservation(1000);

        $this->post($reservation->manageUrl(), ['action' => 'pay'])->assertRedirect();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Pozdní storno – klient zaplatí', $reservation->cancellation_reason);
        $this->assertSame(PaymentStatus::Unpaid, $reservation->payment_status);

        $payment = $reservation->payments()->first();
        $this->assertNotNull($payment);
        $this->assertSame(500, $payment->amount);
        $this->assertSame(PaymentMethod::Qr, $payment->method);
        $this->assertSame(PaymentStatus::Unpaid, $payment->status);
        // The variable symbol is derived from the payment's own numeric id.
        $this->assertSame((string) $payment->number, $payment->variable_symbol);

        Notification::assertSentTo($reservation->client, ReservationStornoPaymentNotification::class);
    }

    public function test_storno_pay_is_idempotent(): void
    {
        Notification::fake();

        $reservation = $this->stornoReservation(1000);

        $this->post($reservation->manageUrl(), ['action' => 'pay'])->assertRedirect();
        $this->post($reservation->manageUrl(), ['action' => 'pay'])->assertRedirect();

        $this->assertSame(1, $reservation->payments()->count());
    }

    public function test_storno_doctor_note_flags_the_reservation_and_emails_the_client(): void
    {
        Notification::fake();

        $reservation = $this->stornoReservation();

        $this->post($reservation->manageUrl(), ['action' => 'doctor'])->assertRedirect();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertNotNull($reservation->doctor_note_requested_at);

        Notification::assertSentTo($reservation->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationDoctorNote;
        });
    }

    public function test_storno_doctor_note_notifies_staff_in_the_database(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $reservation = $this->stornoReservation();

        $this->post($reservation->manageUrl(), ['action' => 'doctor'])->assertRedirect();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $admin->unreadNotifications()->count());
    }

    public function test_storno_deactivate_cancels_and_deactivates_the_account(): void
    {
        Notification::fake();

        $reservation = $this->stornoReservation();

        $this->post($reservation->manageUrl(), ['action' => 'deactivate'])->assertRedirect();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Pozdní storno – bez úhrady', $reservation->cancellation_reason);
        $this->assertNotNull($reservation->client->fresh()->deactivated_at);

        Notification::assertSentTo($reservation->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationCancelled;
        });
    }

    public function test_storno_payment_email_embeds_the_qr_and_payment_details(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
        Setting::query()->where('key', 'payments.iban')->first()->update(['value' => 'CZ6508000000192000145399']);

        $reservation = $this->stornoReservation(1000);
        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $mail = (new ReservationStornoPaymentNotification($reservation, $payment))->toMail($reservation->client);
        $html = $mail->viewData['html'] ?? '';

        $this->assertStringContainsString('data:image/png', $html);
        $this->assertStringContainsString('CZ6508000000192000145399', $html);
        $this->assertStringContainsString('500 Kč', $html);
        $this->assertStringContainsString($payment->variable_symbol, $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    // --- Support classes -----------------------------------------------------

    public function test_storno_fee_is_a_percentage_of_the_service_price(): void
    {
        $this->seed(SettingsSeeder::class);

        $reservation = $this->stornoReservation(1200);

        // Default storno fee is 50% of the price.
        $this->assertSame(600, $reservation->stornoFee());
    }

    public function test_payment_autofills_number_and_variable_symbol(): void
    {
        $payment = Payment::factory()->create(['variable_symbol' => null]);

        $this->assertNotNull($payment->number);
        $this->assertSame((string) $payment->number, $payment->variable_symbol);
    }

    public function test_payment_note_resolves_tokens_from_the_payable_and_payment(): void
    {
        $this->seed(SettingsSeeder::class);

        $service = Service::factory()->create(['name' => 'Lymfodrenáž (60 min)']);
        $reservation = $this->reservation(['service_id' => $service->id]);
        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $note = PaymentNote::render($payment);

        $this->assertStringContainsString('Lymfodrenáž (60 min)', $note);
        $this->assertStringContainsString('VS '.$payment->variable_symbol, $note);
    }

    public function test_qr_platba_builds_a_czk_spayd_descriptor(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::query()->where('key', 'payments.iban')->first()->update(['value' => 'CZ6508000000192000145399']);

        $reservation = $this->stornoReservation(1000);
        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $spayd = QrPlatba::spayd($payment);

        $this->assertStringStartsWith('SPD*1.0*', $spayd);
        $this->assertStringContainsString('ACC:CZ6508000000192000145399', $spayd);
        $this->assertStringContainsString('AM:500.00', $spayd);
        $this->assertStringContainsString('CC:CZK', $spayd);
        $this->assertStringContainsString('VS:'.$payment->variable_symbol, $spayd);
        $this->assertStringStartsWith('data:image/png', QrPlatba::dataUri($payment));
    }

    // --- Deactivation enforcement --------------------------------------------

    public function test_deactivated_user_cannot_access_the_client_panel(): void
    {
        $panel = Filament::getPanel('client');

        $active = User::factory()->customer()->create();
        $deactivated = User::factory()->customer()->create(['deactivated_at' => now()]);

        $this->assertTrue($active->canAccessPanel($panel));
        $this->assertFalse($deactivated->canAccessPanel($panel));
    }

    public function test_deactivated_client_cannot_book_via_the_wizard(): void
    {
        $service = Service::factory()->create();
        $deactivated = User::factory()->customer()->create(['deactivated_at' => now()]);

        $data = new ReservationBookingData(
            service: $service,
            therapistId: (string) Str::uuid(),
            date: '2026-07-20',
            startTime: '10:00',
            firstName: 'Jana',
            lastName: 'Nováková',
            email: $deactivated->email,
            phone: '+420 604 123 456',
            client: $deactivated,
        );

        $creator = app(CreateReservationFromWizard::class);
        $method = new \ReflectionMethod($creator, 'resolveClient');

        $this->expectException(DeactivatedClientException::class);
        $method->invoke($creator, $data);
    }

    // --- Admin ---------------------------------------------------------------

    public function test_admin_marks_a_storno_payment_paid(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->stornoReservation(1000);
        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('markPaid')->table($payment));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);
    }

    public function test_admin_reactivates_a_deactivated_account(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create(['deactivated_at' => now()]);

        Livewire::test(ListClients::class)
            ->callAction(TestAction::make('reactivate')->table($client));

        $this->assertNull($client->fresh()->deactivated_at);
    }

    public function test_admin_cancel_action_cancels_with_reason_and_emails_the_client(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->reservation(['status' => ReservationStatus::Confirmed]);

        Livewire::test(EditReservation::class, ['record' => $reservation->getKey()])
            ->callAction('cancelReservation', [
                'cancellation_reason' => 'Terapeut onemocněl',
                'notify_client' => true,
            ]);

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Terapeut onemocněl', $reservation->cancellation_reason);

        Notification::assertSentTo($reservation->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationCancelled;
        });
    }
}
