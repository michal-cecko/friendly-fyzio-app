<?php

namespace App\Http\Controllers;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationStornoPaymentNotification;
use App\Notifications\ReservationTemplateNotification;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The single passwordless "manage reservation" page, reached via one signed magic
 * link ({@see Reservation::manageUrl()}). It hosts every customer action for a
 * reservation. A free self-cancel is available only while the reservation is still
 * Pending AND outside the storno window; once it is Confirmed or inside the window
 * (and carries a fee), cancelling instead requires the storno decision — pay / doctor's
 * note / deactivate ({@see Reservation::requiresStornoDecision()}).
 * The link itself expires at the visit start, so no action is possible afterwards.
 *
 * GET only renders the state-appropriate page; opening the link — including by e-mail
 * scanners — never mutates. Each action is an explicit POST, then a redirect back to
 * the signed GET URL (PRG) so a refresh can't re-submit.
 */
class ReservationManageController extends Controller
{
    public function show(Reservation $reservation): View
    {
        return view('reservations.manage', [
            'reservation' => $reservation->loadMissing('service.cancellationRule', 'therapist.user', 'client', 'payments'),
        ]);
    }

    public function submit(Request $request, Reservation $reservation): RedirectResponse
    {
        match ($request->input('action')) {
            'confirm' => $this->confirm($reservation),
            'cancel' => $this->cancel($reservation),
            'pay' => $this->pay($reservation),
            'doctor' => $this->doctorNote($reservation),
            'deactivate' => $this->deactivate($reservation),
            default => null,
        };

        return redirect()->to($reservation->manageUrl());
    }

    /**
     * Confirm a pending reservation. Guarded by the Pending check so a double-submit
     * (already Confirmed) never re-sends the confirmation e-mail.
     */
    private function confirm(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationConfirmed));
    }

    /**
     * Free cancellation — allowed for an active reservation that does not (yet) require
     * the storno decision: still Pending and outside the storno window, or carrying no
     * fee. Otherwise the customer must resolve the storno decision instead.
     */
    private function cancel(Reservation $reservation): void
    {
        $isActive = in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true);

        if (! $isActive || $reservation->requiresStornoDecision()) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Zrušeno klientem',
        ]);

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationCancelled));
    }

    /**
     * Storno cancel, "I won't come but I'll pay": cancel, raise an unpaid storno Payment
     * (Czech QR-Platba), and e-mail the customer the payment instructions + QR.
     */
    private function pay(Reservation $reservation): void
    {
        if (! $reservation->requiresStornoDecision()) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Pozdní storno – klient zaplatí',
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        /** @var Payment $payment */
        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => $reservation->stornoFee(),
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $reservation->client?->notify(new ReservationStornoPaymentNotification($reservation, $payment));
    }

    /**
     * Storno cancel, "I was ill and will supply a doctor's note": cancel, waive the fee
     * pending review, flag the reservation, notify staff, and acknowledge the customer.
     */
    private function doctorNote(Reservation $reservation): void
    {
        if (! $reservation->requiresStornoDecision()) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Pozdní storno – potvrzení od lékaře',
            'doctor_note_requested_at' => now(),
        ]);

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationDoctorNote));

        $this->notifyStaffOfDoctorNote($reservation);
    }

    /**
     * Storno cancel, "I won't come and won't pay": cancel and fully deactivate the
     * account (blocks login + online booking).
     */
    private function deactivate(Reservation $reservation): void
    {
        if (! $reservation->requiresStornoDecision()) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Pozdní storno – bez úhrady',
        ]);

        $reservation->client?->update(['deactivated_at' => now()]);

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationCancelled));
    }

    private function notifyStaffOfDoctorNote(Reservation $reservation): void
    {
        $admins = User::query()->where('role', UserRole::Admin)->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Storno s potvrzením od lékaře')
            ->body(($reservation->client?->name ?? 'Klient').' zrušil rezervaci a doloží potvrzení od lékaře. Storno poplatek je pozastaven.')
            ->icon('heroicon-o-document-text')
            ->warning()
            ->sendToDatabase($admins);
    }
}
