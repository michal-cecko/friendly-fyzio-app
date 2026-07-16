<?php

namespace Tests\Feature\Payments;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\SettingValueType;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\MarkNoShowAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RequestPaymentAndNoShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_request_payment_creates_unpaid_qr_payment_and_sends_email(): void
    {
        Notification::fake();

        $reservation = $this->reservation(800, daysAgo: 1);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('requestPayment')->table($reservation), [
                'amount' => 800,
                'due_at' => today()->addDays(7)->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $payment = $reservation->payments()->sole();

        $this->assertSame(PaymentStatus::Unpaid, $payment->status);
        $this->assertSame(PaymentMethod::Qr, $payment->method);
        $this->assertTrue($payment->due_at->isSameDay(today()->addDays(7)));

        Notification::assertSentTo(
            $reservation->client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationUnpaid
                && $notification->extraTokens['vs'] === (string) $payment->variable_symbol,
        );
    }

    public function test_request_payment_hidden_when_pending_unpaid_payment_exists(): void
    {
        $reservation = $this->reservation(800, daysAgo: 1);

        $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        Livewire::test(ListReservations::class)
            ->assertActionHidden(TestAction::make('requestPayment')->table($reservation));
    }

    public function test_mark_no_show_cancels_creates_fee_payment_and_sends_email(): void
    {
        Notification::fake();

        Setting::updateOrCreate(
            ['key' => 'payments.no_show_fee_percent'],
            ['value' => '100', 'type' => SettingValueType::Integer, 'label' => 'x', 'group' => 'Test', 'sort' => 0],
        );

        $reservation = $this->reservation(800, daysAgo: 2);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('markNoShow')->table($reservation), [
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(MarkNoShowAction::CANCELLATION_REASON, $reservation->cancellation_reason);

        $payment = $reservation->payments()->sole();

        $this->assertSame(800, $payment->amount);
        $this->assertSame(PaymentStatus::Unpaid, $payment->status);
        $this->assertNotNull($payment->due_at);

        Notification::assertSentTo(
            $reservation->client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationNoShow,
        );
    }

    public function test_no_show_fee_settles_reservation_when_paid(): void
    {
        $reservation = $this->reservation(800, daysAgo: 2);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('markNoShow')->table($reservation), [
                'notify_client' => false,
            ]);

        $payment = $reservation->payments()->sole();

        $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);
    }

    public function test_no_show_hidden_for_future_or_cancelled_reservations(): void
    {
        $future = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'reservation_date' => today()->addDays(3)->toDateString(),
        ]);

        $cancelled = $this->reservation(800, daysAgo: 2, status: ReservationStatus::Cancelled);

        Livewire::test(ListReservations::class)
            ->assertActionHidden(TestAction::make('markNoShow')->table($future))
            ->assertActionHidden(TestAction::make('markNoShow')->table($cancelled));
    }

    private function reservation(int $price, int $daysAgo, ReservationStatus $status = ReservationStatus::Confirmed): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => $price])->getKey(),
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'reservation_date' => today()->subDays($daysAgo)->toDateString(),
        ]);
    }
}
