<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Finance\Resources\Payments\Pages\ListPayments;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Correcting and removing payments — a mistyped amount or a payment recorded on
 * the wrong record has to be fixable without touching the database by hand.
 */
class PaymentEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_editing_the_amount_down_reopens_the_payable(): void
    {
        $reservation = $this->reservation(800);
        $payment = $this->payment($reservation, 800, PaymentStatus::Paid);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);

        $this->relationManager($reservation)
            ->callAction(TestAction::make('edit')->table($payment), [
                'amount' => 300,
                'method' => PaymentMethod::Qr->value,
                'status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(300, $payment->fresh()->amount);
        // 300 of 800 no longer covers the reservation.
        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);
    }

    public function test_editing_the_amount_up_settles_the_payable(): void
    {
        $reservation = $this->reservation(800);
        $payment = $this->payment($reservation, 300, PaymentStatus::Paid);

        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);

        $this->relationManager($reservation)
            ->callAction(TestAction::make('edit')->table($payment), [
                'amount' => 800,
                'method' => PaymentMethod::Qr->value,
                'status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);
    }

    public function test_deleting_a_payment_reopens_the_payable(): void
    {
        $reservation = $this->reservation(800);
        $payment = $this->payment($reservation, 800, PaymentStatus::Paid);

        $this->relationManager($reservation)
            ->callAction(TestAction::make('delete')->table($payment))
            ->assertHasNoActionErrors()
            // The owning page shows the payable's cached status, so it has to
            // hear about this too.
            ->assertDispatched(PaymentsRelationManager::REFRESH_EVENT);

        $this->assertDatabaseMissing(Payment::class, ['id' => $payment->getKey()]);
        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);
    }

    /**
     * A paid payment may be removed as well — but the cash receipt is an
     * accounting document and only detaches from it.
     */
    public function test_deleting_a_cash_payment_keeps_its_receipt(): void
    {
        InvoiceSeries::factory()->receipt()->asDefault()->create(['prefix' => 'PPD']);

        $reservation = $this->reservation(800);
        $payment = $this->payment($reservation, 800, PaymentStatus::Paid, PaymentMethod::Cash);

        $receipt = $payment->cashReceipt()->first();
        $this->assertNotNull($receipt);

        $this->relationManager($reservation)
            ->callAction(TestAction::make('delete')->table($payment))
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing(Payment::class, ['id' => $payment->getKey()]);
        $this->assertDatabaseHas($receipt::class, [
            'id' => $receipt->getKey(),
            'payment_id' => null,
        ]);
    }

    public function test_finance_table_can_edit_and_delete_a_payment(): void
    {
        $reservation = $this->reservation(800);
        $payment = $this->payment($reservation, 800, PaymentStatus::Paid);

        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('edit')->table($payment), [
                'amount' => 500,
                'method' => PaymentMethod::Cash->value,
                'status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(500, $payment->fresh()->amount);
        $this->assertSame(PaymentMethod::Cash, $payment->fresh()->method);

        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('delete')->table($payment))
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing(Payment::class, ['id' => $payment->getKey()]);
    }

    private function relationManager(Reservation $reservation): Testable
    {
        return Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $reservation,
            'pageClass' => ViewReservation::class,
        ]);
    }

    private function payment(
        Reservation $reservation,
        int $amount,
        PaymentStatus $status,
        PaymentMethod $method = PaymentMethod::Qr,
    ): Payment {
        return $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => $amount,
            'method' => $method,
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
        ]);
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
